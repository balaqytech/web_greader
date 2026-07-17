<?php

use App\Actions\Applications\GenerateApplicationContractAction;
use App\Actions\Support\CreatePdfAction;
use App\Models\Application;
use App\States\Applications\AwaitingBranchReview;
use App\States\Applications\AwaitingContractSignature;
use App\States\Applications\Cancelled;
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
    // Configure the template and guardian data first, then generate — the version freezes an
    // immutable rendered_body from the data as it stands at generation, so display replays that
    // snapshot rather than re-rendering current data.
    $application = Application::factory()->awaitingApplicationCompletion()->create($overrides);
    $application->program->update(['contract' => 'Guardian: $parent_name$ | Student: $student_name$']);
    $application->refresh();

    app(GenerateApplicationContractAction::class)->handle($application);
    $application->update(['status' => AwaitingContractSignature::class]);

    return $application->fresh();
}

function pngSignature(): string
{
    // A genuine 1x1 PNG, not merely bytes behind a matching data-URI prefix.
    return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
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

it('rejects rendering a contract with a null token expiry', function () {
    $application = contractApplication();
    $application->contract->update(['token_expires_at' => null]);

    $this->get(route('contract.show', $application->contract->token))
        ->assertOk()
        ->assertSee(__('admin.application.contract_invalid_or_expired'));
});

it('rejects signing a contract with a null token expiry', function () {
    $application = contractApplication();
    $application->contract->update(['token_expires_at' => null]);

    $this->post(route('contract.sign', $application->contract->token), [
        'signature' => pngSignature(),
    ])
        ->assertOk()
        ->assertSee(__('admin.application.contract_invalid_or_expired'));

    expect($application->fresh()->status)->toBeInstanceOf(AwaitingContractSignature::class);
});

it('rejects rendering a contract whose application has moved past signature', function () {
    $application = contractApplication();
    $application->update(['status' => AwaitingBranchReview::$name]);

    $this->get(route('contract.show', $application->contract->token))
        ->assertOk()
        ->assertSee(__('admin.application.contract_invalid_or_expired'));
});

it('rejects signing a contract whose application is in a terminal state', function () {
    $application = contractApplication();
    $application->update(['status' => Cancelled::$name]);

    $this->post(route('contract.sign', $application->contract->token), [
        'signature' => pngSignature(),
    ])
        ->assertOk()
        ->assertSee(__('admin.application.contract_invalid_or_expired'));

    expect($application->fresh()->contract->signed_at)->toBeNull();
});

it('rejects signing with a non-image PNG payload without a server error', function () {
    $application = contractApplication();

    $this->post(route('contract.sign', $application->contract->token), [
        'signature' => 'data:image/png;base64,'.base64_encode('not a real png'),
    ])
        ->assertOk()
        ->assertSee(__('alerts.application.invalid_signature_image'));

    expect($application->fresh()->status)->toBeInstanceOf(AwaitingContractSignature::class);
});

it('rejects an oversized signature payload without a server error', function () {
    $application = contractApplication();

    $this->post(route('contract.sign', $application->contract->token), [
        'signature' => 'data:image/png;base64,'.str_repeat('A', 2_000_001),
    ])
        ->assertOk()
        ->assertSee(__('alerts.application.signature_too_large'));

    expect($application->fresh()->status)->toBeInstanceOf(AwaitingContractSignature::class);
});
