<?php

use App\Models\Branch;
use App\Models\User;
use App\Support\Api\FasihServiceAbilities;
use App\Support\Api\FasihServiceAccount;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate(FasihServiceAccount::Role, 'web');
    Role::findOrCreate('branch_manager', 'web');
});

it('creates a branchless service account and issues a token with the exact abilities and expiry', function () {
    $this->artisan('fasih:issue-token', ['email' => 'fasih@example.com'])
        ->assertSuccessful();

    $user = User::where('email', 'fasih@example.com')->firstOrFail();

    expect($user->branch_id)->toBeNull()
        ->and($user->hasRole(FasihServiceAccount::Role))->toBeTrue();

    $token = PersonalAccessToken::query()->where('tokenable_id', $user->id)->firstOrFail();

    expect($token->abilities)->toEqualCanonicalizing(FasihServiceAbilities::all())
        ->and($token->expires_at)->not->toBeNull()
        ->and($token->expires_at->isBetween(now()->addDays(89), now()->addDays(91)))->toBeTrue();
});

it('reuses an existing clean branchless service user without creating a duplicate', function () {
    $user = User::factory()->create(['email' => 'fasih@example.com', 'branch_id' => null]);
    $user->assignRole(FasihServiceAccount::Role);

    $this->artisan('fasih:issue-token', ['email' => 'fasih@example.com'])
        ->assertSuccessful();

    expect(User::where('email', 'fasih@example.com')->count())->toBe(1);
});

it('refuses to convert a user assigned to a branch', function () {
    $branch = Branch::factory()->create();
    User::factory()->create(['email' => 'staff@example.com', 'branch_id' => $branch->id]);

    $this->artisan('fasih:issue-token', ['email' => 'staff@example.com'])
        ->assertFailed();

    expect(PersonalAccessToken::query()->count())->toBe(0);
});

it('refuses to convert a user holding an operational role', function () {
    $user = User::factory()->create(['email' => 'manager@example.com', 'branch_id' => null]);
    $user->assignRole('branch_manager');

    $this->artisan('fasih:issue-token', ['email' => 'manager@example.com'])
        ->assertFailed();

    expect(PersonalAccessToken::query()->count())->toBe(0);
});

it('revokes existing tokens with --revoke-existing before issuing the new one', function () {
    $user = User::factory()->create(['email' => 'fasih@example.com', 'branch_id' => null]);
    $user->assignRole(FasihServiceAccount::Role);
    $user->createToken('old', ['leads:read']);

    $this->artisan('fasih:issue-token', ['email' => 'fasih@example.com', '--revoke-existing' => true])
        ->assertSuccessful();

    $tokens = PersonalAccessToken::query()->where('tokenable_id', $user->id)->get();

    expect($tokens)->toHaveCount(1)
        ->and($tokens->first()->abilities)->toEqualCanonicalizing(FasihServiceAbilities::all());
});
