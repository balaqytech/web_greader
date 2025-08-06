<?php

namespace App\Filament\Admin\Actions\ProgramEnrollment;

use Filament\Forms;
use Filament\Tables\Actions\Action;
use App\Models\ProgramEnrollment;
use App\States\Enrollment\Signed;
use App\States\Enrollment\Pending;

class UploadContract
{
    public static function make(): Action
    {
        return Action::make('upload_contract')
            ->label(__('admin.program_enrollment.upload_contract'))
            ->visible(fn (ProgramEnrollment $record) => $record->status->equals(Pending::class))
            ->modalWidth('md')
            ->color('info')
            ->icon('heroicon-o-arrow-up-tray')
            ->form([
                Forms\Components\FileUpload::make('contract_pdf')
                    ->label(__('admin.program_enrollment.contract_pdf'))
                    ->required(),
            ])
            ->action(function (ProgramEnrollment $record, array $data) {
                $record->status->transitionTo(Signed::class, $data['contract_pdf']);
            });
    }
}
