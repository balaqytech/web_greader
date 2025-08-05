<?php

namespace App\States\Enrollment;

use App\Enums\EnrollmentStatus;

class Draft extends EnrollmentState
{
    public static $name = EnrollmentStatus::DRAFT->value;

    public function getLabel(): string
    {
        return __('admin.enrollment.statuses.draft');
    }

    public function color(): string
    {
        return 'gray';
    }
} 