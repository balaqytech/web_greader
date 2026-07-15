<?php

use App\Models\Affiliate;
use App\Models\Application;
use App\Models\Lead;
use App\Models\Student;
use App\States\Affiliates\Verified;
use App\States\Applications\Accepted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * BranchScope applies to Lead and Application, which the affiliate dashboard queries via
 * Affiliate::leads()/applications(). Under the `affiliate` guard, Auth::user() returns an
 * App\Models\Affiliate — not the operational App\Models\User BranchScope's branch rules are
 * defined for — so the scope constrains Lead/Application directly to affiliate_id for this
 * guard instead of calling BranchAccess (which would fatal calling hasRole()/can() on a
 * model that has neither).
 */
it('constrains a direct Lead/Application query to the authenticated affiliate\'s own affiliate_id', function () {
    $affiliateA = Affiliate::factory()->create(['status' => Verified::$name]);
    $affiliateB = Affiliate::factory()->create(['status' => Verified::$name]);

    $leadA = Lead::factory()->create(['affiliate_id' => $affiliateA->id]);
    Lead::factory()->create(['affiliate_id' => $affiliateB->id]);

    $applicationA = Application::factory()->create(['affiliate_id' => $affiliateA->id]);
    Application::factory()->create(['affiliate_id' => $affiliateB->id]);

    $this->actingAs($affiliateA, 'affiliate');

    // Not going through Affiliate::leads()/applications() at all — this proves BranchScope
    // itself enforces the affiliate_id boundary, not merely the relation's own where clause.
    expect(Lead::query()->pluck('id')->all())->toBe([$leadA->id])
        ->and(Application::query()->pluck('id')->all())->toBe([$applicationA->id]);
});

it('keeps Affiliate::leads()/applications() relations isolated to that affiliate\'s own records', function () {
    $affiliateA = Affiliate::factory()->create(['status' => Verified::$name]);
    $affiliateB = Affiliate::factory()->create(['status' => Verified::$name]);

    $leadA = Lead::factory()->create(['affiliate_id' => $affiliateA->id]);
    Lead::factory()->create(['affiliate_id' => $affiliateB->id]);

    $applicationA = Application::factory()->create(['affiliate_id' => $affiliateA->id]);
    Application::factory()->create(['affiliate_id' => $affiliateB->id]);

    $this->actingAs($affiliateA, 'affiliate');

    expect(fn () => $affiliateA->leads()->count())->not->toThrow(Throwable::class)
        ->and(fn () => $affiliateA->applications()->count())->not->toThrow(Throwable::class);

    expect($affiliateA->leads()->count())->toBe(1)
        ->and($affiliateA->leads()->pluck('id')->all())->toBe([$leadA->id])
        ->and($affiliateA->applications()->count())->toBe(1)
        ->and($affiliateA->applications()->pluck('id')->all())->toBe([$applicationA->id]);
});

it('returns no records for a branch-scoped model an affiliate has no ownership concept over', function () {
    Student::factory()->create();

    $affiliate = Affiliate::factory()->create(['status' => Verified::$name]);
    $this->actingAs($affiliate, 'affiliate');

    expect(Student::query()->count())->toBe(0);
});

it('renders the affiliate dashboard for an authenticated affiliate', function () {
    $affiliate = Affiliate::factory()->create(['status' => Verified::$name]);
    Lead::factory()->create(['affiliate_id' => $affiliate->id]);

    $this->actingAs($affiliate, 'affiliate')
        ->get(route('affiliate.dashboard'))
        ->assertOk()
        ->assertSee($affiliate->name);
});

it('excludes another affiliate\'s leads and applications from the dashboard\'s counts', function () {
    $affiliate = Affiliate::factory()->create(['status' => Verified::$name]);
    $otherAffiliate = Affiliate::factory()->create(['status' => Verified::$name]);

    Lead::factory()->count(2)->create(['affiliate_id' => $affiliate->id]);
    Lead::factory()->count(5)->create(['affiliate_id' => $otherAffiliate->id]);

    Application::factory()->create(['affiliate_id' => $affiliate->id, 'status' => Accepted::$name]);
    Application::factory()->count(3)->create(['affiliate_id' => $otherAffiliate->id, 'status' => Accepted::$name]);

    $inProcessApplication = Application::factory()->create(['affiliate_id' => $affiliate->id]);
    expect($inProcessApplication->status)->not->toBeInstanceOf(Accepted::class);
    Application::factory()->count(4)->create(['affiliate_id' => $otherAffiliate->id]);

    $this->actingAs($affiliate, 'affiliate');

    Livewire::test('pages::affiliate.dashboard')
        ->assertSet('leadsCount', 2)
        ->assertSet('approvedApplicationsCount', 1)
        ->assertSet('inProcessApplicationsCount', 1);
});
