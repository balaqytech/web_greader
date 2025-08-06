<?php

namespace App\Filament\Admin\Actions\ProgramEnrollment;

use Filament\Forms;
use Filament\Forms\Get;
use App\Models\Discount;
use App\States\Enrollment\Draft;
use App\Models\ProgramEnrollment;
use App\States\Enrollment\Pending;
use Filament\Tables\Actions\Action;

class AddDiscounts
{
    public static function make(): Action
    {
        return Action::make('add_discounts')
            ->label(__('admin.program_enrollment.add_discounts'))
            ->visible(fn(ProgramEnrollment $record) => $record->status->equals(Draft::class))
            ->modalWidth('md')
            ->icon('heroicon-o-receipt-percent')
            ->color('success')
            ->form([
                Forms\Components\Placeholder::make('program_name')
                    ->label(__('admin.program_enrollment.program_name'))
                    ->content(fn(ProgramEnrollment $record) => $record->program->name),
                Forms\Components\Placeholder::make('base_price')
                    ->label(__('admin.program_enrollment.program_base_price'))
                    ->content(fn(ProgramEnrollment $record) => $record->program->base_price),
                Forms\Components\Placeholder::make('final_price')
                    ->label(__('admin.program_enrollment.final_price'))
                    ->content(function (ProgramEnrollment $record, Get $get) {
                        $discounts = Discount::whereIn('id', $get('discount_id'))->get();
                        $final_price = calculate_enrollment_price($record, $discounts);

                        return $final_price->value();
                    }),
                Forms\Components\Select::make('discount_id')
                    ->label(__('admin.program_enrollment.discount'))
                    ->options(Discount::all()->pluck('name', 'id'))
                    ->live()
                    ->preload()
                    ->multiple()
                    ->required(),
            ])
            ->action(function (ProgramEnrollment $record, array $data) {
                $record->status->transitionTo(Pending::class, Discount::whereIn('id', $data['discount_id'])->get());
            });
    }
}
