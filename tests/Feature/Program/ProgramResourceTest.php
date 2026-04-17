<?php

use App\Enums\ProgramType;
use App\Filament\Resources\Programs\Pages\CreateProgram;
use App\Filament\Resources\Programs\Pages\EditProgram;
use App\Models\Branch;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('it can create a program with branches', function () {
    $branches = Branch::factory()->count(2)->create();

    Livewire::test(CreateProgram::class)
        ->fillForm([
            'name' => 'Test Program',
            'type' => ProgramType::Academic->value,
            'base_price' => 100,
            'accept_installments' => false,
            'is_open' => true,
            'is_active' => true,
            'sort_order' => 0,
            'branches' => [
                ['branch_id' => $branches[0]->id, 'price' => 50.00],
                ['branch_id' => $branches[1]->id, 'price' => 75.00],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $program = Program::query()->where('name', 'Test Program')->first();
    expect($program)->not->toBeNull();
    expect($program->branches)->toHaveCount(2);
    expect((float) $program->branches->firstWhere('id', $branches[0]->id)->pivot->price)->toBe(50.00);
    expect((float) $program->branches->firstWhere('id', $branches[1]->id)->pivot->price)->toBe(75.00);
});

test('it can edit a program and update branches', function () {
    $program = Program::factory()->create();
    $branches = Branch::factory()->count(3)->create();

    // Attach initial branches
    $program->branches()->attach([
        $branches[0]->id => ['price' => 10.00],
        $branches[1]->id => ['price' => 20.00],
    ]);

    Livewire::test(EditProgram::class, ['record' => $program->getRouteKey()])
        ->assertFormSet([
            'name' => $program->name,
        ])
        ->fillForm([
            'base_price' => 100,
            'branches' => [
                ['branch_id' => $branches[1]->id, 'price' => 99.00],
                ['branch_id' => $branches[2]->id, 'price' => 150.00],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $program->refresh();
    expect($program->branches)->toHaveCount(2);
    expect($program->branches->pluck('id')->toArray())->toEqualCanonicalizing([
        $branches[1]->id,
        $branches[2]->id,
    ]);
});
