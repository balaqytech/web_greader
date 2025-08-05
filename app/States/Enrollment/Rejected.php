<?php

namespace App\States\Enrollment;

use App\Enums\EnrollmentStatus;

class Rejected extends EnrollmentState
{
    public static $name = EnrollmentStatus::REJECTED->value;

    public function getLabel(): string
    {
        return __('admin.enrollment.statuses.rejected');
    }

    public function color(): string
    {
        return 'danger';
    }
} 