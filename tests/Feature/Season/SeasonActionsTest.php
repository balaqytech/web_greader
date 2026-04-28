<?php

use App\Actions\Season\CloseSeason;
use App\Actions\Season\CreateSeason;
use App\Actions\Season\OpenSeason;
use App\Actions\Season\UpdateSeason;
use App\Enums\ProgramType;
use App\Models\Season;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

// ─── CreateSeason ─────────────────────────────────────────────────────────────

test('it cannot create a season when the end date is after today', function () {
    expect(fn() => app(CreateSeason::class)->execute([
        'name' => 'Academic 2026-2027',
        'type' => ProgramType::Academic->value,
        'start_date' => now()->subDay()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
    ]))->toThrow(ValidationException::class);
});

test('a new season is activated automatically when no active season of that type exists', function () {
    $season = app(CreateSeason::class)->execute([
        'name' => 'Academic 2025-2026',
        'type' => ProgramType::Academic->value,
        'start_date' => now()->subMonths(9)->toDateString(),
        'end_date' => now()->toDateString(),
    ]);

    expect($season->is_active)->toBeTrue();
});

test('a new season is created as inactive when an active season of the same type already exists', function () {
    Season::factory()->academic()->create(['is_active' => true]);

    $season = app(CreateSeason::class)->execute([
        'name' => 'Academic 2025-2026 Copy',
        'type' => ProgramType::Academic->value,
        'start_date' => now()->subMonths(9)->toDateString(),
        'end_date' => now()->toDateString(),
    ]);

    expect($season->is_active)->toBeFalse();
    expect(Season::count())->toBe(2);
});

test('one active academic and one active summer season can coexist', function () {
    Season::factory()->academic()->create(['is_active' => true]);

    $summer = app(CreateSeason::class)->execute([
        'name' => 'Summer 2025',
        'type' => ProgramType::Summer->value,
        'start_date' => now()->subMonths(3)->toDateString(),
        'end_date' => now()->toDateString(),
    ]);

    expect($summer->is_active)->toBeTrue();
    expect(Season::query()->active()->count())->toBe(2);
});

test('a third season is created as inactive even if it is a different type when two seasons are already active', function () {
    // Both slots are filled: one academic, one summer
    Season::factory()->academic()->create(['is_active' => true]);
    Season::factory()->summer()->create(['is_active' => true]);

    // Summer is already active, so this one comes in inactive
    $second = app(CreateSeason::class)->execute([
        'name' => 'Summer 2025 B',
        'type' => ProgramType::Summer->value,
        'start_date' => now()->subMonths(2)->toDateString(),
        'end_date' => now()->toDateString(),
    ]);

    expect($second->is_active)->toBeFalse();
});

// ─── OpenSeason ───────────────────────────────────────────────────────────────

test('it cannot open a season when another active season of the same type exists', function () {
    Season::factory()->academic()->create(['is_active' => true]);
    $inactive = Season::factory()->academic()->create(['is_active' => false]);

    expect(fn() => app(OpenSeason::class)->execute($inactive))
        ->toThrow(ValidationException::class, 'يُسمح بموسم واحد نشط فقط لكل نوع.');
});

test('it cannot re-open a permanently closed season', function () {
    $closed = Season::factory()->academic()->closed()->create();

    expect(fn() => app(OpenSeason::class)->execute($closed))
        ->toThrow(ValidationException::class, 'لا يمكن إعادة فتح موسم مغلق نهائياً.');
});

test('it can open an inactive season when no active season of that type exists', function () {
    $inactive = Season::factory()->academic()->create(['is_active' => false]);

    $opened = app(OpenSeason::class)->execute($inactive);

    expect($opened->is_active)->toBeTrue();
});

// ─── CloseSeason ──────────────────────────────────────────────────────────────

test('it can close an active season and marks it as permanently closed', function () {
    $season = Season::factory()->summer()->create(['is_active' => true]);

    $closed = app(CloseSeason::class)->execute($season);

    expect($closed->is_active)->toBeFalse();
    expect($closed->is_closed)->toBeTrue();
});

// ─── UpdateSeason ─────────────────────────────────────────────────────────────

test('it validates active season constraints when changing the type of an active season', function () {
    Season::factory()->academic()->create(['is_active' => true]);

    $season = Season::factory()->summer()->create(['is_active' => true]);

    expect(fn() => app(UpdateSeason::class)->execute($season, [
        'name' => $season->name,
        'type' => ProgramType::Academic->value,
        'start_date' => now()->subMonths(3)->toDateString(),
        'end_date' => now()->toDateString(),
    ]))->toThrow(ValidationException::class, 'يُسمح بموسم واحد نشط فقط لكل نوع.');
});

test('it can update a season name without affecting its active status', function () {
    $season = Season::factory()->academic()->create(['is_active' => true]);

    $updated = app(UpdateSeason::class)->execute($season, [
        'name' => 'Renamed Academic',
        'type' => ProgramType::Academic->value,
        'start_date' => now()->subMonths(9)->toDateString(),
        'end_date' => now()->toDateString(),
    ]);

    expect($updated->name)->toBe('Renamed Academic');
    expect($updated->is_active)->toBeTrue();
});
