<?php

namespace App\Actions\Applications;

use App\Actions\Support\CreatePdfAction;
use App\Models\Application;
use App\Models\ApplicationContract;
use App\States\Applications\AwaitingBranchReview;
use App\States\Applications\AwaitingContractSignature;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

final class SignContractOnlineAction
{
    public function execute(ApplicationContract $applicationContract, string $base64Signature, string $contract): Application
    {
        if (! $applicationContract->application->status instanceof AwaitingContractSignature) {
            throw new InvalidArgumentException(__('alerts.application.application_not_waiting_for_contract'));
        }

        if (! $applicationContract->application->hasValidContractToken()) {
            throw new InvalidArgumentException(__('alerts.application.contract_token_invalid_or_expired'));
        }

        // Decode base64 signature
        $imageParts = explode(';base64,', $base64Signature);
        if (count($imageParts) !== 2) {
            throw new InvalidArgumentException(__('alerts.application.invalid_signature_data'));
        }

        $imageTypeAux = explode('image/', $imageParts[0]);
        if (count($imageTypeAux) !== 2) {
            throw new InvalidArgumentException(__('alerts.application.invalid_signature_format'));
        }

        $imageType = $imageTypeAux[1];
        $imageBase64 = base64_decode($imageParts[1]);

        $filename = 'contract_signature_'.Str::random(10).'.'.$imageType;
        $signaturePath = 'contracts/signatures/'.$filename;
        $pdfPath = 'pdfs/contracts/'.time().'_'.Str::random(8).'.pdf';

        try {
            // Write artifacts and persist inside one guarded boundary. Signature storage,
            // PDF generation, and the database work are all compensated on failure — not
            // just database failures. The guarded transition locks the row and rejects a
            // stale state, so a replay cannot overwrite the first signer's artifacts.
            Storage::disk('public')->put($signaturePath, $imageBase64);

            $fileUrl = app(CreatePdfAction::class)->execute('pdf.contract', $pdfPath, [
                'title' => 'test',
                'contract' => $contract,
                'signature' => Storage::url($signaturePath),
            ]);

            DB::transaction(function () use ($applicationContract, $signaturePath, $fileUrl) {
                $applicationContract->update([
                    'signed_at' => now(),
                    'signed_by_applicant' => true,
                    'signature_path' => $signaturePath,
                    'file_path' => $fileUrl,
                ]);

                $applicationContract->application->status->transitionTo(
                    AwaitingBranchReview::class,
                    notes: __('alerts.application.application_contract_signed_online_by_applicant')
                );
            });
        } catch (Throwable $e) {
            Storage::disk('public')->delete([$signaturePath, $pdfPath]);

            throw $e;
        }

        return $applicationContract->application->fresh();
    }
}
