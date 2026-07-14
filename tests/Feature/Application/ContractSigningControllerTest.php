<?php

use App\Actions\Support\CreatePdfAction;
use App\Models\Application;
use App\States\Applications\AwaitingBranchReview;
use App\States\Applications\AwaitingContractSignature;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    app()->bind(CreatePdfAction::class, fn () => new class
    {
        public function execute(string $view, string $path, array $data): string
        {
            Storage::disk('public')->put($path, 'pdf-bytes');

            return Storage::url($path);
        }
    });
});

function contractApplication(array $overrides = []): Application
{
    $application = Application::factory()->awaitingContractSignature()->create($overrides);
    $application->program->update(['contract' => 'Guardian: $parent_name$ | Student: $student_name$']);

    return $application->fresh();
}

function pngSignature(): string
{
    return 'data:image/png;base64,'.base64_encode('fake-png-bytes');
}

it('renders the signing form for a valid unsigned contract', function () {
    $application = contractApplication();

    $this->get(route('contract.show', $application->contract->token))
        ->assertOk()
        ->assertSee($application->student_name);
});

it('rejects rendering an expired contract token', function () {
    $application = contractApplication();
    $application->contract->update(['token_expires_at' => now()->subDay()]);

    $this->get(route('contract.show', $application->contract->token))
        ->assertOk()
        ->assertSee(__('admin.application.contract_invalid_or_expired'));
});

it('rejects rendering an already signed-off (uploaded) contract', function () {
    $application = contractApplication();
    // Uploaded signed copy: file_path + signed_at, no signature_path.
    $application->contract->update(['file_path' => 'contracts/uploads/signed.pdf', 'signed_at' => now()]);

    $this->get(route('contract.show', $application->contract->token))
        ->assertOk()
        ->assertSee(__('admin.application.contract_invalid_or_expired'));
});

it('resolves a relative guardian consistently in the rendered contract', function () {
    $application = contractApplication([
        'father_is_guardian' => false,
        'mother_is_guardian' => false,
        'relative_name' => 'Relative Guardian',
        'relative_id_number' => '77777777',
    ]);

    $this->get(route('contract.show', $application->contract->token))
        ->assertOk()
        ->assertSee('Relative Guardian');
});

it('accepts an electronic signature and advances to branch review', function () {
    $application = contractApplication();

    $this->post(route('contract.sign', $application->contract->token), [
        'signature' => pngSignature(),
    ])->assertOk();

    $application->refresh();

    expect($application->status)->toBeInstanceOf(AwaitingBranchReview::class)
        ->and($application->contract->isSignedOff())->toBeTrue();
});

it('rejects signing an expired contract on submit', function () {
    $application = contractApplication();
    $application->contract->update(['token_expires_at' => now()->subDay()]);

    $this->post(route('contract.sign', $application->contract->token), [
        'signature' => pngSignature(),
    ])
        ->assertOk()
        ->assertSee(__('admin.application.contract_invalid_or_expired'));

    expect($application->fresh()->status)->toBeInstanceOf(AwaitingContractSignature::class);
});

it('rejects signing an already signed-off contract on submit', function () {
    $application = contractApplication();
    $application->contract->update(['file_path' => 'contracts/uploads/signed.pdf', 'signed_at' => now()]);

    $this->post(route('contract.sign', $application->contract->token), [
        'signature' => pngSignature(),
    ])
        ->assertOk()
        ->assertSee(__('admin.application.contract_invalid_or_expired'));
});
