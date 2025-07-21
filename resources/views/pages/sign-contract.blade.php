<?php

use Filament\Forms;
use Filament\Forms\Form;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\ProgramEnrollment;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Saade\FilamentAutograph\Forms\Components\SignaturePad;

new #[Layout('layouts.app')] class extends Component implements HasForms {
    use InteractsWithForms;

    public ?array $data = [];
    public $programEnrollment;
    public $contract;

    public function mount($programEnrollment): void
    {
        $this->programEnrollment = ProgramEnrollment::findOrFail($programEnrollment);

        if ($this->programEnrollment->isSigned()) {
            abort(401, __('alerts.program_enrollment_already_signed'));
        }

        $variables = [
            'program_name' => $this->programEnrollment->program->name,
            'parent_name' => $this->programEnrollment->student->parentAccount->name,
            'student_name' => $this->programEnrollment->student->name,
            'enrollment_date' => $this->programEnrollment->created_at->format('Y-m-d'),
            // Add more variables as needed
        ];

        $this->contract = $this->parseContract($this->programEnrollment->program->contract, $variables);

        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([SignaturePad::make('signature')->label(__('frontend.signature'))->required()])
            ->statePath('data')
            ->columns(2);
    }

    public function create(): void
    {
        dd($this->form->getState());
    }

    function parseContract($template, $variables)
    {
        foreach ($variables as $key => $value) {
            $template = str_replace('$' . $key . '$', $value, $template);
        }
        return $template;
    }
}; ?>

<div>
    <section class="mt-4">
        <div class="wrapper prose prose-slate">
            <h1 class="text-3xl text-center font-bold text-slate-700">{{ __('frontend.contract') }}
                {{ $this->programEnrollment->program->name }}</h1>
            {!! eval('?>' . Blade::compileString(html_entity_decode($this->contract))) !!}
        </div>
    </section>

    <section class="mt-4">
        <div class="wrapper max-w-xl">
            <form wire:submit="create">
                {{ $this->form }}
                <x-primary-button type="submit">
                    {{ __('frontend.send') }}
                </x-primary-button>
            </form>

            <x-filament-actions::modals />
        </div>
    </section>
</div>
