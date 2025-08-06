<?php

namespace App\Livewire;

use Filament\Forms;
use App\Enums\Gender;
use App\Models\Branch;
use App\Models\Program;
use App\Models\Student;
use Livewire\Component;
use Filament\Forms\Form;
use App\Models\AcademicYear;
use App\Models\ParentAccount;
use Livewire\Attributes\Layout;
use App\Models\ProgramEnrollment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use App\Enums\RelationshipWithParent;
use Illuminate\Support\Facades\Blade;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Forms\Concerns\InteractsWithForms;

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
                    Forms\Components\Wizard\Step::make(__('frontend.program_register.parent_info'))
                        ->schema([
                            Forms\Components\TextInput::make('parent.name')
                                ->label(__('frontend.program_register.parent_name'))
                                ->required(),
                            Forms\Components\TextInput::make('parent.email')
                                ->label(__('frontend.program_register.parent_email'))
                                ->email()
                                ->required(),
                            Forms\Components\TextInput::make('parent.phone')
                                ->label(__('frontend.program_register.parent_phone'))
                                ->tel()
                                ->required(),
                            Forms\Components\TextInput::make('parent.address')
                                ->label(__('frontend.program_register.parent_address'))
                                ->required(),
                            Forms\Components\TextInput::make('parent.city')
                                ->label(__('frontend.program_register.parent_city'))
                                ->required(),
                            Forms\Components\Select::make('parent.branch_id')
                                ->label(__('frontend.program_register.parent_branch'))
                                ->options(Branch::all()->pluck('name', 'id'))
                                ->required(),
                            Forms\Components\CheckboxList::make('parent.additional_info.' . __('frontend.program_register.parent_contact_method'))
                                ->label(__('frontend.program_register.parent_contact_method'))
                                ->options([
                                    __('frontend.program_register.parent_contact_method_email') => __('frontend.program_register.parent_contact_method_email'),
                                    __('frontend.program_register.parent_contact_method_phone') => __('frontend.program_register.parent_contact_method_phone'),
                                    __('frontend.program_register.parent_contact_method_whatsapp') => __('frontend.program_register.parent_contact_method_whatsapp'),
                                ])
                                ->columns(3)
                                ->required(),
                            Forms\Components\TextInput::make('parent.additional_info.' . __('frontend.program_register.parent_contact_time'))
                                ->label(__('frontend.program_register.parent_contact_time'))
                                ->required(),
                        ])
                        ->columns(2),
                    Forms\Components\Wizard\Step::make(__('frontend.program_register.student_info'))
                        ->schema([
                            Forms\Components\Repeater::make('students')
                                ->label(__('frontend.program_register.students'))
                                ->addActionLabel(__('frontend.program_register.add_to_students'))
                                ->schema([
                                    Forms\Components\TextInput::make('name')
                                        ->label(__('frontend.program_register.student_name'))
                                        ->required(),
                                    Forms\Components\Select::make('gender')
                                        ->label(__('frontend.program_register.student_gender'))
                                        ->options(Gender::class)
                                        ->required(),
                                    Forms\Components\DatePicker::make('date_of_birth')
                                        ->label(__('frontend.program_register.student_birth_date'))
                                        ->required(),
                                    Forms\Components\Select::make('relationship_with_parent')
                                        ->label(__('frontend.program_register.student_relationship_with_parent'))
                                        ->options(RelationshipWithParent::class)
                                        ->required(),
                                    Forms\Components\Select::make('program_id')
                                        ->label(__('frontend.program_register.student_program'))
                                        ->options(Program::all()->pluck('name', 'id'))
                                        ->required(),
                                    Forms\Components\Select::make('branch_id')
                                        ->label(__('frontend.program_register.student_branch'))
                                        ->options(Branch::all()->pluck('name', 'id'))
                                        ->required(),
                                ])
                                ->columns(2),
                        ]),
                    Forms\Components\Wizard\Step::make(__('frontend.program_register.additional_info'))
                        ->schema([
                            Forms\Components\CheckboxList::make('additional_info.' . __('frontend.program_register.how_did_you_hear_about_us'))
                                ->label(__('frontend.program_register.how_did_you_hear_about_us'))
                                ->options([
                                    __('frontend.program_register.how_did_you_hear_about_us_instagram') => __('frontend.program_register.how_did_you_hear_about_us_instagram'),
                                    __('frontend.program_register.how_did_you_hear_about_us_visit') => __('frontend.program_register.how_did_you_hear_about_us_visit'),
                                    __('frontend.program_register.how_did_you_hear_about_us_friends') => __('frontend.program_register.how_did_you_hear_about_us_friends'),
                                    __('frontend.program_register.how_did_you_hear_about_us_other') => __('frontend.program_register.how_did_you_hear_about_us_other'),
                                ])
                                ->columns(2)
                                ->required(),
                            Forms\Components\Radio::make('additional_info.' . __('frontend.program_register.planning_to_visit'))
                                ->label(__('frontend.program_register.planning_to_visit'))
                                ->options([
                                    __('frontend.program_register.planning_to_visit_yes') => __('frontend.program_register.planning_to_visit_yes'),
                                    __('frontend.program_register.planning_to_visit_no') => __('frontend.program_register.planning_to_visit_no'),
                                ])
                                ->required(),
                            Forms\Components\Textarea::make('additional_info.' . __('frontend.program_register.notes'))
                                ->label(__('frontend.program_register.notes')),
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

        DB::transaction(function () {
            $parent = ParentAccount::where('email', $this->data['parent']['email'])
                ->orWhere('phone', $this->data['parent']['phone'])
                ->first();

            if (!$parent) {
                $this->data['parent']['password'] = bcrypt('123456');
                $parent = ParentAccount::create($this->data['parent']);
            }

            foreach ($this->data['students'] as $studentData) {
                // Create or update student
                $student = $parent->students()->updateOrCreate(
                    ['name' => $studentData['name']],
                    $studentData
                );

                // Create program enrollment for the student
                ProgramEnrollment::updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'program_id' => $studentData['program_id'],
                    ],
                    [
                        'additional_info' => $this->data['additional_info'],
                    ]
                );
            }
        });

        Notification::make()
            ->title(__('frontend.program_register.success_title'))
            ->body(__('frontend.program_register.success_message'))
            ->success()
            ->send();

        $this->data = [];
        $this->form->fill();
    }

    public function render()
    {
        return view('livewire.program-register');
    }
}
