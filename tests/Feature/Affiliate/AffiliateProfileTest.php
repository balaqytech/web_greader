<?php

use App\Models\Affiliate;
use App\States\Affiliates\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->affiliate = Affiliate::factory()->create([
        'status' => Verified::$name,
    ]);
});

test('affiliate can view profile page', function () {
    $this->actingAs($this->affiliate, 'affiliate')
        ->get(route('affiliate.profile.edit'))
        ->assertOk()
        ->assertSee($this->affiliate->name)
        ->assertSee($this->affiliate->whatsapp);
});

test('guest cannot view affiliate profile page', function () {
    $this->get(route('affiliate.profile.edit'))
        ->assertRedirect(route('affiliate.login'));
});

test('affiliate can update profile information', function () {
    $this->actingAs($this->affiliate, 'affiliate')
        ->put(route('affiliate.profile.update'), [
            'name' => 'Updated Name',
            'whatsapp' => '+968500000001',
            'email' => $this->affiliate->email,
        ])
        ->assertRedirect(route('affiliate.profile.edit'))
        ->assertSessionHas('status');

    $this->affiliate->refresh();

    expect($this->affiliate->name)->toBe('Updated Name')
        ->and($this->affiliate->whatsapp)->toBe('+968500000001');
});

test('affiliate profile update requires name', function () {
    $this->actingAs($this->affiliate, 'affiliate')
        ->put(route('affiliate.profile.update'), [
            'name' => '',
            'whatsapp' => '966500000001',
        ])
        ->assertSessionHasErrors('name');
});

test('affiliate profile update requires whatsapp', function () {
    $this->actingAs($this->affiliate, 'affiliate')
        ->put(route('affiliate.profile.update'), [
            'name' => 'Updated Name',
            'whatsapp' => '',
        ])
        ->assertSessionHasErrors('whatsapp');
});

test('affiliate profile update rejects duplicate whatsapp', function () {
    $other = Affiliate::factory()->create([
        'status' => Verified::$name,
    ]);

    $this->actingAs($this->affiliate, 'affiliate')
        ->put(route('affiliate.profile.update'), [
            'name' => 'Updated Name',
            'whatsapp' => $other->whatsapp,
            'email' => $this->affiliate->email,
        ])
        ->assertSessionHasErrors('whatsapp');
});

test('affiliate profile update rejects duplicate whatsapp after normalization', function () {
    Affiliate::factory()->create([
        'status' => Verified::$name,
        'whatsapp' => '+96893918779',
    ]);

    $this->actingAs($this->affiliate, 'affiliate')
        ->put(route('affiliate.profile.update'), [
            'name' => 'Updated Name',
            'whatsapp' => '93918779',
            'email' => $this->affiliate->email,
        ])
        ->assertSessionHasErrors('whatsapp');
});

test('affiliate can keep their own whatsapp when updating profile', function () {
    $this->actingAs($this->affiliate, 'affiliate')
        ->put(route('affiliate.profile.update'), [
            'name' => 'Updated Name',
            'whatsapp' => $this->affiliate->whatsapp,
            'email' => $this->affiliate->email,
        ])
        ->assertRedirect(route('affiliate.profile.edit'))
        ->assertSessionHasNoErrors();
});
