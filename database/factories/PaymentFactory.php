<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentPurpose;
use App\Models\Application;
use App\Models\Payment;
use App\States\Payments\AwaitingVerification;
use App\States\Payments\Expired;
use App\States\Payments\Failed;
use App\States\Payments\Paid;
use App\States\Payments\Pending;
use App\States\Payments\Rejected;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * A pending Thawani registration-fee attempt by default.
     *
     * `branch_id` is derived from the application rather than generated independently: the
     * column is a denormalised copy, and a factory that let the two disagree would
     * manufacture a state the application can never produce — and would quietly invalidate
     * every branch-isolation test built on it.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'branch_id' => fn (array $attributes) => Application::withoutGlobalScopes()
                ->whereKey($attributes['application_id'])
                ->value('branch_id'),
            'purpose' => PaymentPurpose::REGISTRATION_FEE,
            'method' => PaymentMethod::THAWANI,
            'status' => Pending::$name,
            'amount' => '25.000',
            'currency' => 'OMR',
        ];
    }

    /**
     * Ties the attempt to an existing application, keeping the denormalised branch in step.
     */
    public function forApplication(Application $application): static
    {
        return $this->state(fn (array $attributes) => [
            'application_id' => $application->id,
            'branch_id' => $application->branch_id,
        ]);
    }

    public function thawani(): static
    {
        return $this->state(fn (array $attributes) => [
            'method' => PaymentMethod::THAWANI,
            'provider_session_id' => 'sess_'.Str::random(20),
            'provider_checkout_url' => 'https://uatcheckout.thawani.om/pay/'.Str::random(20),
            'provider_expires_at' => now()->addHours(24),
        ]);
    }

    public function bankTransfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'method' => PaymentMethod::BANK_TRANSFER,
            'provider_session_id' => null,
            'provider_checkout_url' => null,
            'provider_expires_at' => null,
        ]);
    }

    public function cash(): static
    {
        return $this->state(fn (array $attributes) => [
            'method' => PaymentMethod::CASH,
            'provider_session_id' => null,
            'provider_checkout_url' => null,
            'provider_expires_at' => null,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Pending::$name,
        ]);
    }

    /**
     * Bank transfers are the only method that passes through verification, so this state
     * implies the method and a receipt.
     */
    public function awaitingVerification(): static
    {
        return $this->bankTransfer()->state(fn (array $attributes) => [
            'status' => AwaitingVerification::$name,
            'receipt_path' => 'receipts/'.Str::random(16).'.pdf',
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Paid::$name,
        ]);
    }

    public function failed(?string $reason = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Failed::$name,
            'failure_reason' => $reason ?? fake()->sentence(),
        ]);
    }

    /**
     * Rejection is a human decision and always carries a reason, so the factory never
     * produces a reasonless one.
     */
    public function rejected(?string $reason = null): static
    {
        return $this->awaitingVerification()->state(fn (array $attributes) => [
            'status' => Rejected::$name,
            'rejection_reason' => $reason ?? fake()->sentence(),
            'verified_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->thawani()->state(fn (array $attributes) => [
            'status' => Expired::$name,
            'provider_expires_at' => now()->subHour(),
        ]);
    }

    public function amount(string $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => $amount,
        ]);
    }
}
