<?php

namespace App\Actions\Applications;

use App\Models\Application;
use App\States\Applications\WaitingContract;
use Illuminate\Support\Facades\Http;

final class SendContractAction
{
    /**
     * Move application to waiting contract and optionally send WhatsApp notification.
     */
    public function execute(Application $application, ?int $transitionedBy = null, ?string $notes = null): Application
    {
        $application->status->transitionTo(WaitingContract::class, transitionedBy: $transitionedBy, notes: $notes);
        $application = $application->fresh();

        // Send WhatsApp link if enabled
        if (config('services.webhooks.contract.enabled') && config('services.webhooks.contract.url')) {
            $phone = $this->getGuardianPhone($application);
            $link = route('contract.show', ['token' => $application->contract_token]);

            try {
                Http::post(config('services.webhooks.contract.url'), [
                    'phone' => $phone,
                    'link' => $link,
                    'ref_no' => $application->ref_no,
                    'student_name' => $application->student_name,
                ]);
            } catch (\Exception $e) {
                // Log or handle error if needed, but don't fail the transition
                report($e);
            }
        }

        return $application;
    }

    private function getGuardianPhone(Application $application): string
    {
        if ($application->father_is_guardian) {
            return $application->father_phone;
        }

        if ($application->mother_is_guardian) {
            return $application->mother_phone;
        }

        return $application->relative_phone;
    }
}
