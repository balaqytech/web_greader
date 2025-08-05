<?php

namespace App\Livewire;

use Filament\Forms;
use App\Enums\Gender;
use App\Models\Branch;
use App\Models\Program;
use Livewire\Component;
use Filament\Forms\Form;
use Livewire\Attributes\Layout;
use Illuminate\Support\HtmlString;
use App\Enums\RelationshipWithParent;
use Illuminate\Support\Facades\Blade;
use Filament\Forms\Contracts\HasForms;
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
                                ->required(),
                            Forms\Components\TextInput::make('parent.phone')
                                ->label(__('frontend.program_register.parent_phone'))
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
                            Forms\Components\CheckboxList::make('parent.additional_info.contact_method')
                                ->label(__('frontend.program_register.parent_contact_method'))
                                ->options(\App\Enums\ContactMethod::class)
                                ->columns(3)
                                ->required(),
                            Forms\Components\TextInput::make('parent.additional_info.contact_time')
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
                                    Forms\Components\DatePicker::make('birth_date')
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
                            Forms\Components\CheckboxList::make('additional_info.how_did_you_hear_about_us')
                                ->label(__('frontend.program_register.how_did_you_hear_about_us'))
                                ->options([
                                    'instagram' => __('frontend.program_register.how_did_you_hear_about_us_instagram'),
                                    'visit' => __('frontend.program_register.how_did_you_hear_about_us_visit'),
                                    'friends' => __('frontend.program_register.how_did_you_hear_about_us_friends'),
                                    'other' => __('frontend.program_register.how_did_you_hear_about_us_other'),
                                ])
                                ->columns(2)
                                ->required(),
                            Forms\Components\Radio::make('additional_info.planning_to_visit')
                                ->label(__('frontend.program_register.planning_to_visit'))
                                ->options([
                                    'yes' => __('frontend.program_register.planning_to_visit_yes'),
                                    'no' => __('frontend.program_register.planning_to_visit_no'),
                                ])
                                ->required(),
                            Forms\Components\Textarea::make('additional_info.notes')
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
        dd($this->data);
    }

    public function render()
    {
        return view('livewire.program-register');
    }
}
