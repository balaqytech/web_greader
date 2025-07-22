<?php

use Filament\Forms;
use Filament\Forms\Form;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\ProgramEnrollment;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Facades\Storage;
use App\Actions\Support\CreatePdfAction;
use Filament\Forms\Concerns\InteractsWithForms;
use App\Actions\ProgramEnrollment\SignContractAction;
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
            'enrollment_date' => $this->programEnrollment->created_at->format('d/m/Y'),
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
        $signature = $this->storeSignature($this->data['signature'] ?? null);
        dd($signature);
    }

    private function parseContract($template, $variables)
    {
        foreach ($variables as $key => $value) {
            $template = str_replace('$' . $key . '$', $value, $template);
        }
        return $template;
    }

    private function storeSignature($signature)
    {
        // Decode the base64 string
        $image = str_replace('data:image/png;base64,', '', $signature);
        $image = str_replace(' ', '+', $image);
        $image = base64_decode($image);

        // Generate a unique filename
        $filename = 'signatures/' . $this->programEnrollment->id . '/signature_' . time() . '.png';

        // Store the image in the public disk
        Storage::disk('public')->put($filename, $image);

        return $filename;
    }
}; ?>

<div>
    <section class="mt-4">
        <div class="wrapper prose prose-slate">
            <h1 class="text-3xl text-center font-bold text-slate-700">{{ __('frontend.contract') }}
                {{ $this->programEnrollment->program->name }}</h1>
            {!! $this->contract !!}
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
