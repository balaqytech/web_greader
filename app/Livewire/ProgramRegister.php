<?php

namespace App\Livewire;

use Filament\Forms;
use App\Enums\Gender;
use App\Models\Branch;
use App\Models\Program;
use App\Models\Student;
use Filament\Forms\Get;
use Livewire\Component;
use Filament\Forms\Form;
use App\Models\AcademicYear;
use App\Models\ParentAccount;
use App\Rules\BranchHasProgram;
use Livewire\Attributes\Layout;
use App\Models\ProgramEnrollment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Log;
use App\Enums\RelationshipWithParent;
use Illuminate\Support\Facades\Blade;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;
use Filament\Forms\Concerns\InteractsWithForms;
use App\Actions\ProgramEnrollment\Register as RegisterAction;

#[Layout('layouts.app')]
class ProgramRegister extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public function mount()
    {
        $this->form->fill();
    }

    public function form(Form $form)
    {
        return $form
            ->schema([
                Forms\Components\Wizard::make([
                    Forms\Components\Wizard\Step::make(__('frontend.program_register.student_info'))
                        ->schema([
                            Forms\Components\TextInput::make('student.name')
                                ->label(__('frontend.program_register.student_name'))
                                ->required(),
                            Forms\Components\Select::make('student.program_id')
                                ->label(__('frontend.program_register.student_program'))
                                ->options(Program::open()->pluck('name', 'id'))
                                ->live()
                                ->reactive()
                                ->required(),
                            Forms\Components\Select::make('student.branch_id')
                                ->label(__('frontend.program_register.student_branch'))
                                ->options(function (Get $get) {
                                    $programId = $get('student.program_id');

                                    if (!$programId) {
                                        return [];
                                    }

                                    return Program::find($programId)?->branches()
                                        ->active()
                                        ->pluck('branches.name', 'branches.id') ?? [];
                                })
                                ->disabled(fn(Get $get) => blank($get('student.program_id')))
                                ->required()
                                ->rule(new BranchHasProgram),
                            Forms\Components\Select::make('student.gender')
                                ->label(__('frontend.program_register.student_gender'))
                                ->options(Gender::class)
                                ->required(),
                            Forms\Components\DatePicker::make('student.date_of_birth')
                                ->label(__('frontend.program_register.student_date_of_birth'))
                                ->required(),
                            Forms\Components\TextInput::make('student.civil_number')
                                ->label(__('frontend.program_register.student_civil_number'))
                                ->required(),
                            Forms\Components\TextInput::make('student.state')
                                ->label(__('frontend.program_register.student_state'))
                                ->required(),
                            Forms\Components\TextInput::make('student.province')
                                ->label(__('frontend.program_register.student_province'))
                                ->required(),
                            Forms\Components\TextInput::make('student.village')
                                ->label(__('frontend.program_register.student_village'))
                                ->required(),
                            Forms\Components\TextInput::make('student.house_number')
                                ->label(__('frontend.program_register.student_house_number'))
                                ->required(),
                            Forms\Components\TextInput::make('student.block_number')
                                ->label(__('frontend.program_register.student_block_number'))
                                ->required(),
                            Forms\Components\Select::make('student.category')
                                ->label(__('frontend.program_register.student_category'))
                                ->options(\App\Enums\StudentCategory::class)
                                ->required(),
                            Forms\Components\TextInput::make('student.parents_relationship')
                                ->label(__('frontend.program_register.student_parents_relationship'))
                                ->required(),
                        ])
                        ->columns(2),
                    Forms\Components\Wizard\Step::make(__('frontend.program_register.father_info'))
                        ->schema([
                            Forms\Components\TextInput::make('father.name')
                                ->label(__('frontend.program_register.father_name'))
                                ->required(),
                            Forms\Components\TextInput::make('father.phone')
                                ->label(__('frontend.program_register.father_phone'))
                                ->required(),
                            Forms\Components\TextInput::make('father.email')
                                ->label(__('frontend.program_register.father_email'))
                                ->required(),
                            Forms\Components\TextInput::make('father.civil_number')
                                ->label(__('frontend.program_register.father_civil_number'))
                                ->required(),
                            Forms\Components\TextInput::make('father.occupation')
                                ->label(__('frontend.program_register.father_occupation'))
                                ->required(),
                            Forms\Components\TextInput::make('father.occupation_address')
                                ->label(__('frontend.program_register.father_occupation_address'))
                                ->required(),
                            Forms\Components\TextInput::make('father.occupation_phone')
                                ->label(__('frontend.program_register.father_occupation_phone'))
                                ->required(),
                        ])
                        ->columns(2),
                    Forms\Components\Wizard\Step::make(__('frontend.program_register.mother_info'))
                        ->schema([
                            Forms\Components\TextInput::make('mother.name')
                                ->label(__('frontend.program_register.mother_name'))
                                ->required(),
                            Forms\Components\TextInput::make('mother.phone')
                                ->label(__('frontend.program_register.mother_phone'))
                                ->required(),
                            Forms\Components\TextInput::make('mother.email')
                                ->label(__('frontend.program_register.mother_email'))
                                ->required(),
                            Forms\Components\TextInput::make('mother.civil_number')
                                ->label(__('frontend.program_register.mother_civil_number'))
                                ->required(),
                            Forms\Components\TextInput::make('mother.occupation')
                                ->label(__('frontend.program_register.mother_occupation'))
                                ->required(),
                            Forms\Components\TextInput::make('mother.occupation_address')
                                ->label(__('frontend.program_register.mother_occupation_address'))
                                ->required(),
                            Forms\Components\TextInput::make('mother.occupation_phone')
                                ->label(__('frontend.program_register.mother_occupation_phone'))
                                ->required(),
                        ])
                        ->columns(2),
                    Forms\Components\Wizard\Step::make(__('frontend.program_register.relative_info'))
                        ->schema([
                            Forms\Components\TextInput::make('relative.name')
                                ->label(__('frontend.program_register.relative_name'))
                                ->required(),
                            Forms\Components\TextInput::make('relative.phone')
                                ->label(__('frontend.program_register.relative_phone'))
                                ->required(),
                            Forms\Components\TextInput::make('relative.email')
                                ->label(__('frontend.program_register.relative_email'))
                                ->required(),
                            Forms\Components\TextInput::make('relative.civil_number')
                                ->label(__('frontend.program_register.relative_civil_number'))
                                ->required(),
                            Forms\Components\TextInput::make('relative.occupation')
                                ->label(__('frontend.program_register.relative_occupation'))
                                ->required(),
                            Forms\Components\TextInput::make('relative.occupation_address')
                                ->label(__('frontend.program_register.relative_occupation_address'))
                                ->required(),
                            Forms\Components\TextInput::make('relative.occupation_phone')
                                ->label(__('frontend.program_register.relative_occupation_phone'))
                                ->required(),
                        ])
                        ->columns(2),
                ])
                    ->submitAction(new HtmlString(Blade::render(<<<BLADE
                            <x-submit-button type="submit" class="mt-4" wire:loading.attr="disabled" wire:target="create">
                                {{ __('frontend.send') }}
                            </x-submit-button>
                        BLADE)))
            ])
            ->statePath('data')
            ->columns(1);
    }

    public function create()
    {
        $this->validate();

        try {
            $registerAction = new RegisterAction();
            $result = $registerAction->execute($this->data);

            Notification::make()
                ->title(__('frontend.program_register.success_title'))
                ->body($result['message'])
                ->success()
                ->send();

            $this->data = [];
            $this->form->fill();
        } catch (ValidationException $e) {
            Notification::make()
                ->title(__('frontend.program_register.error_title'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title(__('frontend.program_register.error_title'))
                ->body(__('frontend.program_register.error_message'))
                ->danger()
                ->send();
        }
    }

    public function render()
    {
        return view('livewire.program-register');
    }
}
