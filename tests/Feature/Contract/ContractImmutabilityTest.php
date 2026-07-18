<?php

declare(strict_types=1);

use App\Exceptions\ContractImmutabilityException;
use App\Models\Application;
use App\Models\ApplicationContract;
use App\Models\User;
use App\States\Contracts\Cancelled;
use App\States\Contracts\Generated;
use App\States\Contracts\Signed;
use App\States\Contracts\Superseded;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('refuses to mutate a frozen field after creation', function (string $column, $value) {
    $contract = ApplicationContract::factory()->create();

    expect(fn () => $contract->update([$column => $value]))
        ->toThrow(ContractImmutabilityException::class);

    // The persisted value is unchanged.
    $original = $contract->getOriginal($column);
    expect(ApplicationContract::find($contract->id)->getAttribute($column))->toEqual($original);
})->with([
    'version' => ['version', 99],
    'data_snapshot' => ['data_snapshot', ['minimum' => ['student_name' => 'tampered'], 'placeholders' => [], 'meta' => ['backfilled' => false]]],
    'rendered_body' => ['rendered_body', 'tampered body'],
    'template_hash' => ['template_hash', hash('sha256', 'tampered')],
]);

it('refuses to repoint the application or the generator', function () {
    $contract = ApplicationContract::factory()->create();
    $otherApplication = Application::factory()->create();

    expect(fn () => $contract->update(['application_id' => $otherApplication->id]))
        ->toThrow(ContractImmutabilityException::class);

    expect(fn () => $contract->fresh()->update(['generated_by' => User::factory()->create()->id]))
        ->toThrow(ContractImmutabilityException::class);
});

it('still allows the signing fields and the Generated -> Signed transition', function () {
    $contract = ApplicationContract::factory()->create();

    $contract->update([
        'signed_at' => now(),
        'signed_by_applicant' => true,
        'signature_path' => 'contracts/signatures/x.png',
        'file_path' => 'contracts/x.pdf',
    ]);

    $contract->status->transitionTo(Signed::class);

    expect($contract->fresh()->status)->toBeInstanceOf(Signed::class)
        ->and($contract->fresh()->isSignedOff())->toBeTrue();
});

it('retains snapshot data and artifacts when superseded', function () {
    $contract = ApplicationContract::factory()->signed()->create();
    $snapshot = $contract->data_snapshot;
    $body = $contract->rendered_body;

    $successor = ApplicationContract::factory()->for($contract->application)->create(['version' => 2]);
    $contract->status->transitionTo(Superseded::class, $successor->id);
    $contract->refresh();

    expect($contract->status)->toBeInstanceOf(Superseded::class)
        ->and($contract->token)->toBeNull()
        ->and($contract->data_snapshot)->toBe($snapshot)
        ->and($contract->rendered_body)->toBe($body)
        ->and($contract->file_path)->not->toBeNull();
});

it('retains snapshot data and artifacts when cancelled', function () {
    $contract = ApplicationContract::factory()->signed()->create();
    $snapshot = $contract->data_snapshot;

    $contract->status->transitionTo(Cancelled::class);
    $contract->refresh();

    expect($contract->status)->toBeInstanceOf(Cancelled::class)
        ->and($contract->token)->toBeNull()
        ->and($contract->data_snapshot)->toBe($snapshot)
        ->and($contract->file_path)->not->toBeNull();
});

it('refuses direct deletion of a contract version', function () {
    $contract = ApplicationContract::factory()->create();

    expect(fn () => $contract->delete())->toThrow(ContractImmutabilityException::class);

    expect(ApplicationContract::find($contract->id))->not->toBeNull();
});

it('leaves the version untouched by generation staying Generated with its token', function () {
    $contract = ApplicationContract::factory()->create();

    expect($contract->status)->toBeInstanceOf(Generated::class)
        ->and($contract->token)->not->toBeNull();
});
