<?php

namespace App\Filament\Admin\Actions\ProgramEnrollment;

use App\States\Enrollment\Draft;
use App\Models\ProgramEnrollment;
use App\States\Enrollment\Canceled;
use Filament\Tables\Actions\Action;

class CancelEnrollment
{
    public static function make(): Action
    {
        return Action::make('cancel_enrollment')
            ->label(__('admin.program_enrollment.cancel_enrollment'))
            ->visible(fn(ProgramEnrollment $record) => $record->status->equals(Draft::class))
            ->modalWidth('md')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('admin.program_enrollment.cancel_enrollment_description'))
            ->action(function (ProgramEnrollment $record) {
                $record->status->transitionTo(Canceled::class);
            });
    }
}
