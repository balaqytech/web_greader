<?php

namespace App\States\Enrollment;

use App\Enums\EnrollmentStatus;

class Canceled extends EnrollmentState
{
    public static $name = EnrollmentStatus::CANCELED->value;

    public function label(): string
    {
        return __('admin.enrollment.statuses.canceled');
    }

    public function color(): string
    {
        return 'secondary';
    }
} 