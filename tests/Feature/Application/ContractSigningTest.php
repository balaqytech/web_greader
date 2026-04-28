<?php

use App\Actions\Applications\SendContractAction;
use App\Models\Application;
use App\States\Applications\DataComplete;
use App\States\Applications\UnderReview;
use App\States\Applications\WaitingContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('data_complete to waiting_contract sets token and expiry', function () {
    $application = Application::factory()->dataComplete()->create();

    $application->status->transitionTo(WaitingContract::class);
    $application->refresh();

    expect($application->status)->toBeInstanceOf(WaitingContract::class)
        ->and($application->contract_token)->not->toBeNull()
        ->and($application->contract_token_expires_at)->not->toBeNull()
        ->and($application->contract_token_expires_at->isFuture())->toBeTrue();
});

it('public contract url resolves with valid token', function () {
    $application = Application::factory()->waitingContract()->create();

    $response = $this->get(route('contract.show', $application->contract_token));

    $response->assertStatus(200);
    $response->assertViewIs('contract.show');
});

it('public contract url returns error with invalid token', function () {
    $response = $this->get(route('contract.show', 'invalid-token'));

    $response->assertStatus(200);
    $response->assertViewIs('contract.error');
});

it('public contract url returns error with expired token', function () {
    $application = Application::factory()->waitingContract()->create([
        'contract_token_expires_at' => now()->subDays(1),
    ]);

    $response = $this->get(route('contract.show', $application->contract_token));

    $response->assertStatus(200);
    $response->assertViewIs('contract.error');
});

it('submits drawn signature and transitions to under_review', function () {
    Storage::fake('private');

    $application = Application::factory()->waitingContract()->create();

    // 1px transparent png base64
    $base64Image = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    $response = $this->post(route('contract.sign', $application->contract_token), [
        'signature' => $base64Image,
    ]);

    $response->assertStatus(200);
    $response->assertViewIs('contract.success');

    $application->refresh();

    expect($application->status)->toBeInstanceOf(UnderReview::class)
        ->and($application->contract_signed_at)->not->toBeNull()
        ->and($application->contract_signed_by_applicant)->toBeTrue()
        ->and($application->contract_signature_path)->not->toBeNull()
        ->and($application->contract_token)->toBeNull()
        ->and($application->contract_token_expires_at)->toBeNull();

    Storage::disk('private')->assertExists($application->contract_signature_path);
});

it('staff upload transitions to under_review with flags', function () {
    $application = Application::factory()->waitingContract()->create();

    $application->status->transitionTo(
        UnderReview::class,
        signedByApplicant: false,
        filePath: 'contracts/uploads/test.pdf'
    );
    $application->refresh();

    expect($application->status)->toBeInstanceOf(UnderReview::class)
        ->and($application->contract_signed_at)->not->toBeNull()
        ->and($application->contract_signed_by_applicant)->toBeFalse()
        ->and($application->contract_file_path)->toBe('contracts/uploads/test.pdf')
        ->and($application->contract_token)->toBeNull();
});

it('waiting_contract to data_complete clears fields', function () {
    $application = Application::factory()->waitingContract()->create();

    $application->status->transitionTo(DataComplete::class);
    $application->refresh();

    expect($application->status)->toBeInstanceOf(DataComplete::class)
        ->and($application->contract_token)->toBeNull()
        ->and($application->contract_token_expires_at)->toBeNull();
});

it('calls webhook when SendContractAction executes', function () {
    Http::fake();
    config()->set('services.webhooks.contract.enabled', true);
    config()->set('services.webhooks.contract.url', 'http://webhook.test');

    $application = Application::factory()->dataComplete()->create();

    app(SendContractAction::class)->execute($application);

    Http::assertSent(function (Request $request) use ($application) {
        return $request->url() == 'http://webhook.test' &&
               $request['ref_no'] == $application->ref_no;
    });
});
