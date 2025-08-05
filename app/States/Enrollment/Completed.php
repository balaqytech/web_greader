<?php

namespace App\States\Enrollment;

use App\Enums\EnrollmentStatus;

class Completed extends EnrollmentState
{
    public static $name = EnrollmentStatus::COMPLETED->value;

    public function getLabel(): string
    {
        return __('admin.enrollment.statuses.completed');
    }

    public function color(): string
    {
        return 'dark';
    }
} 