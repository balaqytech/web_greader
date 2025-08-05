<?php

namespace App\States\Enrollment;

use App\Enums\EnrollmentStatus;

class Approved extends EnrollmentState
{
    public static $name = EnrollmentStatus::APPROVED->value;

    public function label(): string
    {
        return __('admin.enrollment.statuses.approved');
    }

    public function color(): string
    {
        return 'primary';
    }
} 