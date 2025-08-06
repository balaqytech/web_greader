<?php

namespace App\Actions\ProgramEnrollment;

use App\Enums\EnrollmentStatus;
use App\Models\ProgramEnrollment;
use App\States\Enrollment\Signed;

class SignContractAction
{
    public function execute(ProgramEnrollment $programEnrollment, string $contract): \Illuminate\Http\JsonResponse
    {
        if ($programEnrollment->status->equals(Signed::class)) {
            return response()->json([
                'message' => __('alerts.program_enrollment_already_signed'),
            ], 400);
        }

        $programEnrollment->status->transitionTo(Signed::class, $contract);

        return response()->json([
            'message' => __('alerts.contract_signed_successfully'),
            'contract' => $contract,
        ], 200);
    }
}