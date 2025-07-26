<?php

namespace App\Actions\ProgramEnrollment;

use App\Enums\EnrollmentStatus;
use App\Models\ProgramEnrollment;

class SignContractAction
{
    public function execute(ProgramEnrollment $programEnrollment, string $contract): \Illuminate\Http\JsonResponse
    {
        if ($programEnrollment->status === EnrollmentStatus::SIGNED) {
            return response()->json([
                'message' => __('alerts.program_enrollment_already_signed'),
            ], 400);
        }

        $programEnrollment->update([
            'contract_pdf' => $contract,
            'contract_signed_at' => now(),
            'status' => EnrollmentStatus::SIGNED,
        ]);

        return response()->json([
            'message' => __('alerts.contract_signed_successfully'),
            'contract' => $contract,
        ], 200);
    }
}