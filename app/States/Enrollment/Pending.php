<?php

namespace App\States\Enrollment;

use App\Enums\EnrollmentStatus;
use Filament\Support\Contracts\HasLabel;

class Pending extends EnrollmentState
{
    public static $name = EnrollmentStatus::PENDING->value;

    public function getLabel(): string
    {
        return __('admin.enrollment.statuses.pending');
    }

    public function color(): string
    {
        return 'info';
    }
} 