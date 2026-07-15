<?php

use App\Models\Affiliate;
use App\Models\Application;
use App\Models\Lead;
use App\States\Affiliates\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * BranchScope applies to Lead and Application, which the affiliate dashboard queries via
 * Affiliate::leads()/applications(). Under the `affiliate` guard, Auth::user() returns an
 * App\Models\Affiliate — not the operational App\Models\User BranchScope's branch rules are
 * defined for — so the scope must skip its branch logic entirely for this guard rather than
 * fatal calling hasRole()/can() on a model that has neither.
 */
it('lets an authenticated affiliate query its own leads and applications without BranchScope erroring on a non-User guard', function () {
    $affiliateA = Affiliate::factory()->create(['status' => Verified::$name]);
    $affiliateB = Affiliate::factory()->create(['status' => Verified::$name]);

    $leadA = Lead::factory()->create(['affiliate_id' => $affiliateA->id]);
    Lead::factory()->create(['affiliate_id' => $affiliateB->id]);

    $applicationA = Application::factory()->create(['affiliate_id' => $affiliateA->id]);
    Application::factory()->create(['affiliate_id' => $affiliateB->id]);

    $this->actingAs($affiliateA, 'affiliate');

    expect(fn () => $affiliateA->leads()->count())->not->toThrow(Throwable::class)
        ->and(fn () => $affiliateA->applications()->count())->not->toThrow(Throwable::class);

    // Isolation comes entirely from the affiliate_id foreign key on these relations — no
    // branch predicate is added (nor needed) for a non-User guard.
    expect($affiliateA->leads()->count())->toBe(1)
        ->and($affiliateA->leads()->pluck('id')->all())->toBe([$leadA->id])
        ->and($affiliateA->applications()->count())->toBe(1)
        ->and($affiliateA->applications()->pluck('id')->all())->toBe([$applicationA->id]);
});

it('renders the affiliate dashboard for an authenticated affiliate', function () {
    $affiliate = Affiliate::factory()->create(['status' => Verified::$name]);
    Lead::factory()->create(['affiliate_id' => $affiliate->id]);

    $this->actingAs($affiliate, 'affiliate')
        ->get(route('affiliate.dashboard'))
        ->assertOk()
        ->assertSee($affiliate->name);
});
