<?php

namespace App\Filament\Admin\Resources;

use App\Enums\EnrollmentStatus;
use App\Filament\Admin\Resources\EnrollmentResource\Pages;
use App\Models\Enrollment;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EnrollmentResource extends Resource
{
    protected static ?string $model = Enrollment::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation_groups.school');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.enrollments');
    }

    public static function getModelLabel(): string
    {
        return __('admin.enrollment.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.enrollment.plural_label');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('student.full_name')
                    ->label(__('admin.enrollment.student_name')),
                Tables\Columns\TextColumn::make('program.name')
                    ->label(__('admin.enrollment.program_name')),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('admin.enrollment.status'))
                    ->badge(),
                Tables\Columns\TextColumn::make('contract_pdf')
                    ->label(__('admin.enrollment.contract_pdf'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('contract_signed_at')
                    ->label(__('admin.enrollment.contract_signed_at'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('app.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('admin.enrollment.status'))
                    ->options(EnrollmentStatus::class),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make(__('admin.enrollment.enrollment_info'))
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('student.full_name')
                            ->label(__('admin.enrollment.student_name')),
                        Infolists\Components\TextEntry::make('program.name')
                            ->label(__('admin.enrollment.program_name')),
                        Infolists\Components\TextEntry::make('status')
                            ->label(__('admin.enrollment.status')),
                        Infolists\Components\TextEntry::make('contract_signed_at')
                            ->label(__('admin.enrollment.contract_signed_at'))
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('contract_pdf')
                            ->label(__('admin.enrollment.contract_pdf'))
                            ->url(fn(Enrollment $record) => $record->contract_pdf ? asset('storage/' . $record->contract_pdf) : null),
                    ])
                    ->columns(3),
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
            'index' => Pages\ListEnrollments::route('/'),
            'view' => Pages\ViewEnrollment::route('/{record}'),
        ];
    }
}
