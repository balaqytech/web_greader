<?php

use App\Models\Affiliate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('affiliate can register with a normalized whatsapp number', function () {
    $this->post(route('affiliate.register.store'), [
        'name' => 'New Affiliate',
        'whatsapp' => '93918779',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('affiliate.login'));

    expect(Affiliate::query()->where('whatsapp', '+96893918779')->exists())->toBeTrue();
});

test('affiliate registration rejects duplicate whatsapp after normalization', function () {
    Affiliate::factory()->create([
        'whatsapp' => '+96893918779',
    ]);

    $this->post(route('affiliate.register.store'), [
        'name' => 'Duplicate Affiliate',
        'whatsapp' => '93918779',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('whatsapp');

    expect(Affiliate::query()->where('whatsapp', '+96893918779')->count())->toBe(1);
});

test('affiliate registration normalizes whatsapp with country code and no plus sign', function () {
    $this->post(route('affiliate.register.store'), [
        'name' => 'New Affiliate',
        'whatsapp' => '96893918779',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('affiliate.login'));

    expect(Affiliate::query()->where('whatsapp', '+96893918779')->exists())->toBeTrue();
});
