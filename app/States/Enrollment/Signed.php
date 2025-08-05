<?php

namespace App\States\Enrollment;

use App\Enums\EnrollmentStatus;

class Signed extends EnrollmentState
{
    public static $name = EnrollmentStatus::SIGNED->value;

    public function label(): string
    {
        return __('admin.enrollment.statuses.signed');
    }

    public function color(): string
    {
        return 'success';
    }
} 