<?php

namespace App\Filament\Admin\Actions\ProgramEnrollment;

use App\Models\ProgramEnrollment;
use App\States\Enrollment\Signed;
use App\States\Enrollment\Rejected;
use Filament\Tables\Actions\Action;

class RejectEnrollment
{
    public static function make(): Action
    {
        return Action::make('reject_enrollment')
            ->label(__('admin.program_enrollment.reject_enrollment'))
            ->visible(fn(ProgramEnrollment $record) => $record->status->equals(Signed::class))
            ->modalWidth('md')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('admin.program_enrollment.reject_enrollment_description'))
            ->action(function (ProgramEnrollment $record) {
                $record->status->transitionTo(Rejected::class);
            });
    }
}
