<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SubmissionStatus: string implements HasLabel
{
    case NEW = 'new';
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPELETED = 'completed';

    public function getLabel(): string
    {
        return match ($this) {
            self::NEW => __('admin.submission_statuses.new'),
            self::PENDING => __('admin.submission_statuses.pending'),
            self::PROCESSING => __('admin.submission_statuses.processing'),
            self::COMPELETED => __('admin.submission_statuses.completed'),
        };
    }
}
