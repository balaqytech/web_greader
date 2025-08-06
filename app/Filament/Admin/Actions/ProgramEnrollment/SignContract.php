<?php

namespace App\Filament\Admin\Actions\ProgramEnrollment;

use Filament\Actions\Action;
use App\Models\ProgramEnrollment;
use App\States\Enrollment\Pending;

class SignContract
{
    public static function make(): Action
    {
        return Action::make('sign_contract')
            ->label(__('admin.program_enrollment.sign_contract'))
            ->visible(fn(ProgramEnrollment $record) => $record->status->equals(Pending::class))
            ->url(fn(ProgramEnrollment $record) => route('sign-contract', $record), true)
            ->color('primary')
            ->icon('heroicon-o-arrow-top-right-on-square');
    }
}
