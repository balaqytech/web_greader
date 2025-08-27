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
use App\States\Enrollment\Draft;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use App\Models\ProgramEnrollment;
use App\States\Enrollment\Signed;
use App\States\Enrollment\Pending;
use App\States\Enrollment\Approved;
use App\States\Enrollment\Completed;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use App\Filament\Admin\Resources\ProgramEnrollmentResource\Pages;
use App\Filament\Admin\Resources\ProgramEnrollmentResource\RelationManagers;
use App\Filament\Admin\Actions\ProgramEnrollment as ProgramEnrollmentActions;

class ProgramEnrollmentResource extends Resource implements HasShieldPermissions
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
                Tables\Columns\TextColumn::make('student.branch.name')
                    ->label(__('admin.student.branch_id')),
                Tables\Columns\TextColumn::make('student.gender')
                    ->label(__('admin.student.gender')),
                Tables\Columns\TextColumn::make('student.date_of_birth')
                    ->label(__('admin.student.date_of_birth'))
                    ->date('d/m/Y'),
                Tables\Columns\TextColumn::make('student.civil_number')
                    ->label(__('admin.student.civil_number')),
                Tables\Columns\TextColumn::make('student.state')
                    ->label(__('admin.student.state')),
                Tables\Columns\TextColumn::make('student.province')
                    ->label(__('admin.student.province')),
                Tables\Columns\TextColumn::make('student.village')
                    ->label(__('admin.student.village')),
                Tables\Columns\TextColumn::make('student.house_number')
                    ->label(__('admin.student.house_number')),
                Tables\Columns\TextColumn::make('student.block_number')
                    ->label(__('admin.student.block_number')),
                Tables\Columns\TextColumn::make('student.category')
                    ->label(__('admin.student.category')),
                Tables\Columns\TextColumn::make('student.parents_relationship')
                    ->label(__('admin.student.parents_relationship')),
                Tables\Columns\TextColumn::make('student.additional_info.father.name')
                    ->label(__('admin.student.father_name')),
                Tables\Columns\TextColumn::make('student.additional_info.father.email')
                    ->label(__('admin.student.father_email')),
                Tables\Columns\TextColumn::make('student.additional_info.father.phone')
                    ->label(__('admin.student.father_phone')),
                Tables\Columns\TextColumn::make('student.additional_info.father.civil_number')
                    ->label(__('admin.student.father_civil_number')),
                Tables\Columns\TextColumn::make('student.additional_info.father.occupation')
                    ->label(__('admin.student.father_occupation')),
                Tables\Columns\TextColumn::make('student.additional_info.father.occupation_address')
                    ->label(__('admin.student.father_occupation_address')),
                Tables\Columns\TextColumn::make('student.additional_info.father.occupation_phone')
                    ->label(__('admin.student.father_occupation_phone')),
                Tables\Columns\TextColumn::make('student.additional_info.mother.name')
                    ->label(__('admin.student.mother_name')),
                Tables\Columns\TextColumn::make('student.additional_info.mother.email')
                    ->label(__('admin.student.mother_email')),
                Tables\Columns\TextColumn::make('student.additional_info.mother.phone')
                    ->label(__('admin.student.mother_phone')),
                Tables\Columns\TextColumn::make('student.additional_info.mother.civil_number')
                    ->label(__('admin.student.mother_civil_number')),
                Tables\Columns\TextColumn::make('student.additional_info.mother.occupation')
                    ->label(__('admin.student.mother_occupation')),
                Tables\Columns\TextColumn::make('student.additional_info.mother.occupation_address')
                    ->label(__('admin.student.mother_occupation_address')),
                Tables\Columns\TextColumn::make('student.additional_info.mother.occupation_phone')
                    ->label(__('admin.student.mother_occupation_phone')),
                Tables\Columns\TextColumn::make('student.additional_info.relative.name')
                    ->label(__('admin.student.relative_name')),
                Tables\Columns\TextColumn::make('student.additional_info.relative.email')
                    ->label(__('admin.student.relative_email')),
                Tables\Columns\TextColumn::make('student.additional_info.relative.phone')
                    ->label(__('admin.student.relative_phone')),
                Tables\Columns\TextColumn::make('student.additional_info.relative.civil_number')
                    ->label(__('admin.student.relative_civil_number')),
                Tables\Columns\TextColumn::make('student.additional_info.relative.occupation')
                    ->label(__('admin.student.relative_occupation')),
                Tables\Columns\TextColumn::make('student.additional_info.relative.occupation_address')
                    ->label(__('admin.student.relative_occupation_address')),
                Tables\Columns\TextColumn::make('student.additional_info.relative.occupation_phone')
                    ->label(__('admin.student.relative_occupation_phone')),
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
                    ->url(fn($record) => $record->contract_pdf ? asset($record->contract_pdf) : null, true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('program_id')
                    ->label(__('admin.program_enrollment.program_name'))
                    ->options(Program::all()->pluck('name', 'id')),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                \pxlrbt\FilamentExcel\Actions\Tables\ExportAction::make('export'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ActionGroup::make([
                        ProgramEnrollmentActions\AddDiscounts::make(),
                        ProgramEnrollmentActions\UploadContract::make(),
                        ProgramEnrollmentActions\SignContract::make(),
                        ProgramEnrollmentActions\ApproveEnrollment::make(),
                        Tables\Actions\EditAction::make()
                            ->visible(fn(ProgramEnrollment $record) => $record->status->equals(Draft::class) || $record->status->equals(Pending::class)),
                    ])
                        ->dropdown(false),
                    ProgramEnrollmentActions\CancelEnrollment::make(),
                    ProgramEnrollmentActions\RejectEnrollment::make(),

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
                            ->url(fn($record) => $record->contract_pdf ? asset($record->contract_pdf) : null, true)
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
                Infolists\Components\Section::make(__('admin.program_enrollment.invoice_info'))
                    ->schema([
                        Infolists\Components\TextEntry::make('invoice.invoice_number')
                            ->label(__('admin.program_enrollment.invoice_number')),
                        Infolists\Components\TextEntry::make('invoice.amount')
                            ->label(__('admin.program_enrollment.invoice_amount')),
                        Infolists\Components\TextEntry::make('invoice.paid_amount')
                            ->label(__('admin.program_enrollment.invoice_paid_amount')),
                        Infolists\Components\TextEntry::make('invoice.remaining_amount')
                            ->label(__('admin.program_enrollment.invoice_remaining_amount')),
                        Infolists\Components\TextEntry::make('invoice.status')
                            ->label(__('admin.program_enrollment.invoice_status'))
                            ->badge()
                            ->color(fn($state) => $state->color()),
                    ])
                    ->visible(fn(ProgramEnrollment $record) => $record->status->equals(Approved::class) || $record->status->equals(Completed::class))
                    ->columns(2),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\InstallmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProgramEnrollments::route('/'),
            'view' => Pages\ViewProgramEnrollment::route('/{record}'),
        ];
    }

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
        ];
    }
}
