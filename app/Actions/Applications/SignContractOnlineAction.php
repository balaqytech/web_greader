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
use Throwable;

final class SignContractOnlineAction
{
    private const MAX_ENCODED_LENGTH = 2_000_000;

    private const MAX_DECODED_BYTES = 1_500_000;

    public function execute(ApplicationContract $applicationContract, string $base64Signature, string $contract): Application
    {
        $imageBytes = $this->decodeSignature($base64Signature);

        if (! $applicationContract->application->hasSignableContract($applicationContract)) {
            throw new InvalidArgumentException(__('alerts.application.contract_token_invalid_or_expired'));
        }

        $filename = 'contract_signature_'.Str::random(10).'.png';
        $signaturePath = 'contracts/signatures/'.$filename;
        $pdfPath = 'pdfs/contracts/'.time().'_'.Str::random(8).'.pdf';

        try {
            // Write artifacts and persist inside one guarded boundary. Signature storage,
            // PDF generation, and the database work are all compensated on failure — not
            // just database failures. The application row is locked and its contract
            // re-verified against the single authoritative rule before any write, in
            // application -> contract lock order, so a stale replay cannot overwrite the
            // first signer's artifacts or rotate the token.
            Storage::disk('public')->put($signaturePath, $imageBytes);

            $fileUrl = app(CreatePdfAction::class)->execute('pdf.contract', $pdfPath, [
                'title' => 'test',
                'contract' => $contract,
                'signature' => Storage::url($signaturePath),
            ]);

            DB::transaction(function () use ($applicationContract, $signaturePath, $fileUrl) {
                $application = LockApplication::inState($applicationContract->application, AwaitingContractSignature::class);

                $lockedContract = $application->contract()->lockForUpdate()->first();

                if (! $application->hasSignableContract($lockedContract)) {
                    throw new InvalidArgumentException(__('alerts.application.contract_token_invalid_or_expired'));
                }

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
