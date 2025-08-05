<?php

namespace App\States\Enrollment;

use App\Enums\EnrollmentStatus;

class Pending extends EnrollmentState
{
    public static $name = EnrollmentStatus::PENDING->value;

    public function label(): string
    {
        return __('admin.enrollment.statuses.pending');
    }

    public function color(): string
    {
        return 'info';
    }
} 