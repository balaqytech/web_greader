<?php

use App\Models\Affiliate;
use App\Models\User;
use App\States\Affiliates\Pending;
use App\States\Affiliates\Rejected;
use App\States\Affiliates\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('transitions from pending to verified and returns refreshed model', function () {
    $affiliate = Affiliate::factory()->create();
    $user = User::factory()->create();

    expect($affiliate->status)->toBeInstanceOf(Pending::class);

    $affiliate->status->transitionTo(Verified::class, $user);

    $affiliate->refresh();

    expect($affiliate->status)->toBeInstanceOf(Verified::class)
        ->and($affiliate->verified_by)->toBe($user->id)
        ->and($affiliate->verified_at)->not->toBeNull();
});

it('transitions from pending to rejected and returns refreshed model', function () {
    $affiliate = Affiliate::factory()->create();
    $user = User::factory()->create();

    expect($affiliate->status)->toBeInstanceOf(Pending::class);

    $affiliate->status->transitionTo(Rejected::class, $user);

    $affiliate->refresh();

    expect($affiliate->status)->toBeInstanceOf(Rejected::class)
        ->and($affiliate->rejected_by)->toBe($user->id)
        ->and($affiliate->rejected_at)->not->toBeNull();
});

it('transitions from verified to rejected', function () {
    $affiliate = Affiliate::factory()->create();
    $verifier = User::factory()->create();
    $rejector = User::factory()->create();

    $affiliate->status->transitionTo(Verified::class, $verifier);
    $affiliate->refresh();

    $affiliate->status->transitionTo(Rejected::class, $rejector);
    $affiliate->refresh();

    expect($affiliate->status)->toBeInstanceOf(Rejected::class);
});
