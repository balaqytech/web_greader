<?php

namespace App\Actions\Applications;

use App\Actions\Support\CreatePdfAction;
use App\Models\Application;
use App\Models\ApplicationContract;
use App\States\Applications\AwaitingBranchReview;
use App\States\Applications\AwaitingContractSignature;
use App\Support\Applications\LockApplication;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class SignContractOnlineAction
{
    private const MAX_ENCODED_LENGTH = 2_000_000;

    private const MAX_DECODED_BYTES = 1_500_000;

    /**
     * $token is the token the caller actually submitted (from the route/request), bound and
     * re-verified against the freshly locked contract row — not the possibly-stale token on
     * $applicationContract as loaded before the lock. A token invalidated by a concurrent
     * reopen/regenerate cycle must never sign the replacement contract it no longer names.
     */
    public function execute(ApplicationContract $applicationContract, string $token, string $base64Signature): Application
    {
        $imageBytes = $this->decodeSignature($base64Signature);

        if (! $applicationContract->application->hasSignableContract($applicationContract, $token)) {
            throw new InvalidArgumentException(__('alerts.application.contract_token_invalid_or_expired'));
        }

        $filename = 'contract_signature_'.Str::random(10).'.png';
        $signaturePath = 'contracts/signatures/'.$filename;
        $pdfPath = 'pdfs/contracts/'.time().'_'.Str::random(8).'.pdf';

        try {
            // Application -> contract lock order, all in one guarded boundary: the row is
            // locked and re-verified — including that the locked contract's token still
            // matches the one actually submitted — before the contract body is rendered from
            // the *locked* (current) data and before any artifact is written. A stale replay
            // therefore cannot overwrite a later signer's artifacts, rotate the token, or sign
            // a contract it was never shown.
            DB::transaction(function () use ($applicationContract, $token, $imageBytes, $signaturePath, $pdfPath) {
                $application = LockApplication::inState($applicationContract->application, AwaitingContractSignature::class);

                $lockedContract = $application->contract()->lockForUpdate()->first();

                if (! $application->hasSignableContract($lockedContract, $token)) {
                    throw new InvalidArgumentException(__('alerts.application.contract_token_invalid_or_expired'));
                }

                // KNOWN PHASE 0 GAP, not fixed here: this re-renders the contract body from
                // *current* data at signing time — it is not a stored snapshot of the exact
                // body that was actually displayed to the signer when the page was rendered
                // (GET). If the program's contract template or the application's data changes
                // between GET and this POST, the signed PDF can legitimately differ from what
                // the signer saw. Electronic signing is not production-ready until contract
                // generation persists an immutable `rendered_body` (+ `template_hash`) and both
                // display and signing reuse that stored snapshot instead of re-rendering — see
                // the contract-versioning section of docs/target-registration-architecture.md.
                $contractBody = app(RenderApplicationContractAction::class)->execute($application);

                if (! Storage::disk('public')->put($signaturePath, $imageBytes)) {
                    throw new RuntimeException("Failed to write signature image to storage path [{$signaturePath}].");
                }

                // Resolve through the same 'public' disk the signature was actually written
                // to — the default disk resolves URLs through a different serving route.
                $fileUrl = app(CreatePdfAction::class)->execute('pdf.contract', $pdfPath, [
                    'title' => 'test',
                    'contract' => $contractBody,
                    'signature' => Storage::disk('public')->url($signaturePath),
                ]);

                $lockedContract->update([
                    'signed_at' => now(),
                    'signed_by_applicant' => true,
                    'signature_path' => $signaturePath,
                    'file_path' => $fileUrl,
                ]);

                $application->status->transitionTo(
                    AwaitingBranchReview::class,
                    notes: __('alerts.application.application_contract_signed_online_by_applicant')
                );
            }, attempts: 3);
        } catch (Throwable $e) {
            Storage::disk('public')->delete([$signaturePath, $pdfPath]);

            throw $e;
        }

        return $applicationContract->application->fresh();
    }

    /**
     * Strictly decode and validate the submitted signature: a well-formed
     * `data:image/png;base64,...` payload, strict base64 (no lenient/garbage
     * characters), non-empty decoded content, a genuine PNG image (not merely a
     * matching data-URI prefix), and a bounded size. Nothing is written on failure.
     */
    private function decodeSignature(string $base64Signature): string
    {
        if (strlen($base64Signature) > self::MAX_ENCODED_LENGTH) {
            throw new InvalidArgumentException(__('alerts.application.signature_too_large'));
        }

        $imageParts = explode(';base64,', $base64Signature);
        if (count($imageParts) !== 2) {
            throw new InvalidArgumentException(__('alerts.application.invalid_signature_data'));
        }

        $imageTypeAux = explode('image/', $imageParts[0]);
        if (count($imageTypeAux) !== 2 || $imageTypeAux[1] !== 'png') {
            throw new InvalidArgumentException(__('alerts.application.invalid_signature_format'));
        }

        $imageBytes = base64_decode($imageParts[1], strict: true);

        if ($imageBytes === false || $imageBytes === '') {
            throw new InvalidArgumentException(__('alerts.application.invalid_signature_data'));
        }

        if (strlen($imageBytes) > self::MAX_DECODED_BYTES) {
            throw new InvalidArgumentException(__('alerts.application.signature_too_large'));
        }

        $imageInfo = @getimagesizefromstring($imageBytes);

        if ($imageInfo === false || ($imageInfo['mime'] ?? null) !== 'image/png') {
            throw new InvalidArgumentException(__('alerts.application.invalid_signature_image'));
        }

        return $imageBytes;
    }
}
