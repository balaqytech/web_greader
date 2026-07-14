<?php

namespace App\Filament\Resources\Applications\Schemas;

use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class CreateApplicationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Step::make(__('admin.application.student_info'))
                        ->icon('heroicon-o-academic-cap')
                        ->schema([
                            Select::make('branch_id')
                                ->label(__('admin.branch.label'))
                                ->relationship(
                                    'branch',
                                    'name',
                                    modifyQueryUsing: fn (Builder $query) => static::restrictBranchQueryForEmployee($query),
                                )
                                ->default(static::defaultBranchIdForEmployee())
                                ->disabled(static::defaultBranchIdForEmployee() !== null)
                                ->dehydrated()
                                ->required(),
                            Select::make('program_id')
                                ->label(__('admin.program.name'))
                                ->relationship('program', 'name')
                                ->required(),

                            TextInput::make('student_name')
                                ->label(__('admin.application.student_name'))
                                ->required(),
                            Select::make('student_gender')
                                ->label(__('admin.application.student_gender'))
                                ->options(Gender::class)
                                ->required(),
                            DatePicker::make('student_birth_date')
                                ->label(__('admin.application.student_birth_date'))
                                ->required(),
                            TextInput::make('student_civil_number')
                                ->label(__('admin.application.student_civil_number'))
                                ->required(),
                            TextInput::make('student_state')
                                ->label(__('admin.application.student_state'))
                                ->required(),
                            TextInput::make('student_governorate')
                                ->label(__('admin.application.student_governorate'))
                                ->required(),
                            TextInput::make('student_village')
                                ->label(__('admin.application.student_village'))
                                ->required(),
                            TextInput::make('student_house_number')
                                ->label(__('admin.application.student_house_number'))
                                ->required(),
                            TextInput::make('student_parents_social_status')
                                ->label(__('admin.application.student_parents_social_status'))
                                ->required(),
                            Select::make('relationship_with_guardian')
                                ->label(__('admin.application.student_relationship_with_guardian'))
                                ->options(GuardianRelationship::class)
                                ->required(),
                        ])
                        ->columns(3),

                    Step::make(__('admin.application.father_data'))
                        ->icon('heroicon-o-user')
                        ->schema([
                            TextInput::make('father_name')
                                ->label(__('admin.application.father_name'))
                                ->required(),
                            TextInput::make('father_phone')
                                ->label(__('admin.application.father_phone'))
                                ->tel()
                                ->required(),
                            TextInput::make('father_email')
                                ->label(__('admin.application.father_email'))
                                ->email(),
                            TextInput::make('father_id_number')
                                ->label(__('admin.application.father_id_number'))
                                ->required(),
                            TextInput::make('father_occupation')
                                ->label(__('admin.application.father_occupation'))
                                ->required(),
                            TextInput::make('father_work_address')
                                ->label(__('admin.application.father_work_address')),
                            TextInput::make('father_work_phone')
                                ->label(__('admin.application.father_work_phone')),
                            Toggle::make('father_is_guardian')
                                ->label(__('admin.application.father_is_guardian'))
                                ->live()
                                ->afterStateHydrated(function ($component, Get $get) {
                                    $component->state(! $get('mother_is_guardian'));
                                }),
                        ])
                        ->columns(2),

                    Step::make(__('admin.application.mother_data'))
                        ->icon('heroicon-o-user')
                        ->schema([
                            TextInput::make('mother_name')
                                ->label(__('admin.application.mother_name'))
                                ->required(),
                            TextInput::make('mother_phone')
                                ->label(__('admin.application.mother_phone'))
                                ->tel()
                                ->required(),
                            TextInput::make('mother_email')
                                ->label(__('admin.application.mother_email'))
                                ->email(),
                            TextInput::make('mother_id_number')
                                ->label(__('admin.application.mother_id_number'))
                                ->required(),
                            TextInput::make('mother_occupation')
                                ->label(__('admin.application.mother_occupation'))
                                ->required(),
                            TextInput::make('mother_work_address')
                                ->label(__('admin.application.mother_work_address')),
                            TextInput::make('mother_work_phone')
                                ->label(__('admin.application.mother_work_phone')),
                            Toggle::make('mother_is_guardian')
                                ->label(__('admin.application.mother_is_guardian'))
                                ->live()
                                ->afterStateHydrated(function ($component, Get $get) {
                                    $component->state(! $get('father_is_guardian'));
                                }),
                        ])
                        ->columns(2),

                    Step::make(__('admin.application.relative_data'))
                        ->icon('heroicon-o-user-group')
                        ->schema([
                            TextInput::make('relative_name')
                                ->label(__('admin.application.relative_name'))
                                ->required(),
                            TextInput::make('relative_phone')
                                ->label(__('admin.application.relative_phone'))
                                ->tel()
                                ->required(),
                            TextInput::make('relative_email')
                                ->label(__('admin.application.relative_email'))
                                ->email(),
                            TextInput::make('relative_id_number')
                                ->label(__('admin.application.relative_id_number'))
                                ->required(),
                            TextInput::make('relative_occupation')
                                ->label(__('admin.application.relative_occupation'))
                                ->required(),
                            TextInput::make('relative_work_address')
                                ->label(__('admin.application.relative_work_address')),
                            TextInput::make('relative_work_phone')
                                ->label(__('admin.application.relative_work_phone')),
                        ])
                        ->columns(3),
                ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * UX-only restriction: a branch-scoped employee only sees their own branch in the
     * select. This does not replace server-side authorization — CreateLeadWithApplicationAction
     * re-checks the acting user's branch against the submitted branch_id independently, so a
     * tampered request is still rejected even if this query were bypassed.
     */
    private static function restrictBranchQueryForEmployee(Builder $query): Builder
    {
        $branchId = static::defaultBranchIdForEmployee();

        return $branchId === null ? $query : $query->where('id', $branchId);
    }

    private static function defaultBranchIdForEmployee(): ?int
    {
        $user = Auth::user();

        if ($user === null || $user->hasRole('super_admin')) {
            return null;
        }

        return $user->branch_id;
    }
}
