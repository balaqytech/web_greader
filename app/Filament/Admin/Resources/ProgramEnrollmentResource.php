<?php

namespace App\Filament\Admin\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Program;
use App\Models\Student;
use Filament\Infolists;
use App\Models\Discount;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Enums\EnrollmentStatus;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use App\Models\ProgramEnrollment;
use App\States\Enrollment\Signed;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Admin\Actions\ProgramEnrollment\AddDiscounts;
use App\Filament\Admin\Resources\ProgramEnrollmentResource\Pages;
use App\Filament\Admin\Resources\ProgramEnrollmentResource\RelationManagers;
use App\Filament\Admin\Actions\ProgramEnrollment as ProgramEnrollmentActions;

class ProgramEnrollmentResource extends Resource
{
    protected static ?string $model = ProgramEnrollment::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation_groups.applications');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.program_enrollments');
    }

    public static function getModelLabel(): string
    {
        return __('admin.program_enrollment.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.program_enrollment.plural_label');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('student_id')
                    ->label(__('admin.program_enrollment.student_name'))
                    ->required()
                    ->relationship('student', 'name')
                    ->searchable(),
                Forms\Components\Select::make('program_id')
                    ->label(__('admin.program_enrollment.program_name'))
                    ->required()
                    ->relationship('program', 'name'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => $query->with(['student', 'program', 'invoice']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('student.name')
                    ->label(__('admin.program_enrollment.student_name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('program.name')
                    ->label(__('admin.program_enrollment.program_name')),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('admin.program_enrollment.status'))
                    ->badge()
                    ->color(fn($state) => $state->color()),
                Tables\Columns\TextColumn::make('contract_signed_at')
                    ->label(__('admin.program_enrollment.contract_signed_at'))
                    ->date('Y-m-d')
                    ->sortable(),
                Tables\Columns\TextColumn::make('contract_pdf')
                    ->label(__('admin.program_enrollment.contract_pdf'))
                    ->url(fn($record) => $record->contract_pdf ? Storage::url($record->contract_pdf) : null),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('program_id')
                    ->label(__('admin.program_enrollment.program_name'))
                    ->options(Program::all()->pluck('name', 'id')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\ActionGroup::make([
                    AddDiscounts::make(),
                    Tables\Actions\EditAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make(__('admin.program_enrollment.enrollment_info'))
                    ->schema([
                        Infolists\Components\TextEntry::make('student.name')
                            ->label(__('admin.program_enrollment.student_name')),
                        Infolists\Components\TextEntry::make('program.name')
                            ->label(__('admin.program_enrollment.program_name')),
                        Infolists\Components\TextEntry::make('status')
                            ->label(__('admin.program_enrollment.status'))
                            ->badge()
                            ->color(fn($state) => $state->color()),
                        Infolists\Components\TextEntry::make('contract_signed_at')
                            ->label(__('admin.program_enrollment.contract_signed_at'))
                            ->date('d/m/Y')
                            ->visible(fn($record) => $record->status instanceof Signed),
                        Infolists\Components\TextEntry::make('contract_pdf')
                            ->label(__('admin.program_enrollment.contract_pdf'))
                            ->url(fn($record) => $record->contract_pdf ? Storage::url($record->contract_pdf) : null)
                            ->visible(fn($record) => $record->status instanceof Signed),
                        Infolists\Components\TextEntry::make('discounts')
                            ->label(__('admin.program_enrollment.discounts'))
                            ->formatStateUsing(fn($record) => $record->discounts->pluck('name')->implode(', ')),
                        Infolists\Components\TextEntry::make('final_price')
                            ->label(__('admin.program_enrollment.final_price')),
                    ])
                    ->columns(2),
                Infolists\Components\Section::make(__('admin.program_enrollment.additional_info'))
                    ->schema(
                        fn(ProgramEnrollment $record) =>
                        collect($record->additional_info ?? [])
                            ->map(
                                fn($value, $key) => Infolists\Components\TextEntry::make($key)
                                    ->label($key)
                                    ->state($value)
                            )
                            ->toArray()
                    )
                    ->columns(2),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProgramEnrollments::route('/'),
            'view' => Pages\ViewProgramEnrollment::route('/{record}'),
        ];
    }
}
