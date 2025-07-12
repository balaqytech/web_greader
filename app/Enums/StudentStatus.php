<?php

namespace App\Enums;

enum StudentStatus: string implements \Filament\Support\Contracts\HasLabel
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case SUSPENDED = 'suspended';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PENDING => __('admin.student.statuses.pending'),
            self::ACTIVE => __('admin.student.statuses.active'),
            self::INACTIVE => __('admin.student.statuses.inactive'),
            self::SUSPENDED => __('admin.student.statuses.suspended'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::ACTIVE => 'success',
            self::INACTIVE => 'gray',
            self::SUSPENDED => 'danger',
        };
    }
}