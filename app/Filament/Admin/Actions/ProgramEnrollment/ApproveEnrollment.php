<?php

namespace App\Filament\Admin\Actions\ProgramEnrollment;

use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use App\States\Enrollment\Signed;
use App\Models\ProgramEnrollment;
use App\States\Enrollment\Approved;
use Filament\Forms;

class ApproveEnrollment
{
    public static function make(): Action
    {
        return Action::make('approve_enrollment')
            ->label(__('admin.program_enrollment.approve_enrollment'))
            ->visible(fn(ProgramEnrollment $record) => $record->status->equals(Signed::class))
            ->modalWidth('3xl')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->form([
                Forms\Components\Placeholder::make('final_price')
                    ->label(__('admin.program_enrollment.final_price'))
                    ->content(fn(ProgramEnrollment $record) => $record->final_price->value())
                    ->columnSpanFull(),
                Forms\Components\Repeater::make('installments')
                    ->label(__('admin.program_enrollment.installments'))
                    ->schema([
                        Forms\Components\TextInput::make('amount')
                            ->label(__('admin.program_enrollment.installment_amount'))
                            ->required()
                            ->numeric()
                            ->minValue(0.01),
                        Forms\Components\DatePicker::make('due_date')
                            ->label(__('admin.program_enrollment.installment_due_date'))
                            ->required(),
                    ])
                    ->columns(2)
                    ->minItems(1)
                    ->reorderable(false),
            ])
            ->action(function (ProgramEnrollment $record, array $data) {
                // Calculate the final price
                $finalPrice = $record->final_price->value();
                
                // Calculate the sum of installment amounts
                $installmentSum = collect($data['installments'])->sum('amount');
                
                // Check if the sum equals the final price (with a small tolerance for floating point precision)
                if (abs($installmentSum - $finalPrice) > 0.01) {
                    Notification::make()
                        ->title(__('admin.program_enrollment.installment_sum_error_title'))
                        ->body(__('admin.program_enrollment.installment_sum_error_body', [
                            'final_price' => $finalPrice,
                            'installment_sum' => $installmentSum,
                            'difference' => abs($installmentSum - $finalPrice)
                        ]))
                        ->danger()
                        ->send();
                    
                    return;
                }
                
                // If validation passes, proceed with the transition
                $record->status->transitionTo(Approved::class, $data['installments']);
                
                Notification::make()
                    ->title(__('admin.program_enrollment.approved_success_title'))
                    ->body(__('admin.program_enrollment.approved_success_body'))
                    ->success()
                    ->send();
            });
    }
}
