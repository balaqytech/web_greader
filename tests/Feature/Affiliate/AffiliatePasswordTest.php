<?php

use App\Models\Affiliate;
use App\States\Affiliates\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->affiliate = Affiliate::factory()->create([
        'status' => Verified::$name,
        'password' => 'current-password',
    ]);
});

test('affiliate can view password page', function () {
    $this->actingAs($this->affiliate, 'affiliate')
        ->get(route('affiliate.password.edit'))
        ->assertOk();
});

test('guest cannot view affiliate password page', function () {
    $this->get(route('affiliate.password.edit'))
        ->assertUnauthorized();
});

test('affiliate can update password', function () {
    $this->actingAs($this->affiliate, 'affiliate')
        ->put(route('affiliate.password.update'), [
            'current_password' => 'current-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertRedirect(route('affiliate.password.edit'))
        ->assertSessionHas('status');

    $this->affiliate->refresh();

    expect(Hash::check('new-password-123', $this->affiliate->password))->toBeTrue();
});

test('affiliate cannot update password with wrong current password', function () {
    $this->actingAs($this->affiliate, 'affiliate')
        ->put(route('affiliate.password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertSessionHasErrors('current_password');
});

test('affiliate password update requires confirmation', function () {
    $this->actingAs($this->affiliate, 'affiliate')
        ->put(route('affiliate.password.update'), [
            'current_password' => 'current-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'different-password',
        ])
        ->assertSessionHasErrors('password');
});

test('affiliate password update requires current password', function () {
    $this->actingAs($this->affiliate, 'affiliate')
        ->put(route('affiliate.password.update'), [
            'current_password' => '',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertSessionHasErrors('current_password');
});
