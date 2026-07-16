<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Support\Money\OmrAmount;
use App\Support\Settings\PaymentSettings;
use App\Support\Settings\SettingsRepository;
use Database\Seeders\PaymentSettingsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    app(SettingsRepository::class)->flush();
});

function paymentSettings(): PaymentSettings
{
    return app(PaymentSettings::class);
}

function settingsRepository(): SettingsRepository
{
    return app(SettingsRepository::class);
}

it('declares both payment settings keys as unset when seeded', function () {
    $this->seed(PaymentSettingsSeeder::class);

    expect(Setting::query()->count())->toBe(2)
        ->and(Setting::query()->whereNotNull('value')->count())->toBe(0);

    foreach (PaymentSettings::KEYS as $key) {
        expect(Setting::query()->where('key', $key)->exists())->toBeTrue();
    }
});

it('blocks payments while the registration fee is unset', function () {
    $this->seed(PaymentSettingsSeeder::class);

    expect(paymentSettings()->registrationFee())->toBeNull()
        ->and(paymentSettings()->isRegistrationFeeConfigured())->toBeFalse();
});

it('blocks payments when the fee key does not exist at all', function () {
    expect(paymentSettings()->registrationFee())->toBeNull()
        ->and(paymentSettings()->isRegistrationFeeConfigured())->toBeFalse();
});

/**
 * The whole point of seeding NULL: an operator re-running seeders on an environment where
 * the fee is already configured must never have it silently reset, which would take
 * payments down.
 */
it('never resets an already configured fee when reseeded', function () {
    $this->seed(PaymentSettingsSeeder::class);

    paymentSettings()->setRegistrationFee(OmrAmount::fromString('25.000'));

    $this->seed(PaymentSettingsSeeder::class);
    $this->seed(PaymentSettingsSeeder::class);

    expect(paymentSettings()->registrationFee()?->value)->toBe('25.000')
        ->and(Setting::query()->count())->toBe(2);
});

it('round-trips the fee exactly', function (string $value) {
    paymentSettings()->setRegistrationFee(OmrAmount::fromString($value));

    expect(paymentSettings()->registrationFee()?->value)->toBe($value);
})->with(['0.001', '25.000', '25.500', '1.005', '150.750']);

/**
 * Snapshot stability: what is read back is byte-identical to what was stored, so a fee
 * snapshotted onto a payment can never drift by a baisa through a float round-trip.
 */
it('stores the fee as an exact decimal string, not a float', function () {
    paymentSettings()->setRegistrationFee(OmrAmount::fromString('0.070'));

    expect(DB::table('settings')->where('key', PaymentSettings::REGISTRATION_FEE_AMOUNT)->value('value'))
        ->toBe('0.070')
        ->and(paymentSettings()->registrationFee()?->toBaisa())->toBe(70);
});

it('treats a stored zero fee as unconfigured', function () {
    settingsRepository()->set(PaymentSettings::REGISTRATION_FEE_AMOUNT, '0.000');

    // atLeast(): isRegistrationFeeConfigured() delegates to registrationFee(), so asserting
    // both below parses — and warns — more than once. The warning staying loud on every read
    // of a corrupt fee is intended, so the count is not pinned here.
    Log::shouldReceive('warning')->atLeast()->once();

    expect(paymentSettings()->registrationFee())->toBeNull()
        ->and(paymentSettings()->isRegistrationFeeConfigured())->toBeFalse();
});

/**
 * Blocking payments is the safe direction for a corrupt value, and a read accessor that
 * threw would break the very settings page needed to repair it.
 */
it('treats a malformed stored fee as unconfigured and logs it', function (string $stored) {
    settingsRepository()->set(PaymentSettings::REGISTRATION_FEE_AMOUNT, $stored);

    Log::shouldReceive('warning')->atLeast()->once();

    expect(paymentSettings()->registrationFee())->toBeNull()
        ->and(paymentSettings()->isRegistrationFeeConfigured())->toBeFalse();
})->with(['25.5', 'abc', '', '-5.000', '25']);

it('still exposes a malformed stored fee raw so it can be repaired', function () {
    settingsRepository()->set(PaymentSettings::REGISTRATION_FEE_AMOUNT, '25.5');

    expect(paymentSettings()->rawRegistrationFeeAmount())->toBe('25.5');
});

it('treats blank bank instructions as unconfigured', function (?string $stored) {
    settingsRepository()->set(PaymentSettings::BANK_TRANSFER_INSTRUCTIONS, $stored);

    expect(paymentSettings()->bankTransferInstructions())->toBeNull()
        ->and(paymentSettings()->isBankTransferConfigured())->toBeFalse();
})->with([null, '', '   ']);

it('normalises blank bank instructions to null on write', function () {
    paymentSettings()->setBankTransferInstructions('   ');

    expect(DB::table('settings')->where('key', PaymentSettings::BANK_TRANSFER_INSTRUCTIONS)->value('value'))
        ->toBeNull();
});

it('exposes configured bank instructions', function () {
    paymentSettings()->setBankTransferInstructions("Bank: X\nIBAN: OM00 0000");

    expect(paymentSettings()->bankTransferInstructions())->toBe("Bank: X\nIBAN: OM00 0000")
        ->and(paymentSettings()->isBankTransferConfigured())->toBeTrue();
});

/**
 * Each setting gates only its own method: bank transfer being unconfigured must not block
 * the fee, and vice versa.
 */
it('gates each payment method independently', function () {
    paymentSettings()->setRegistrationFee(OmrAmount::fromString('25.000'));

    expect(paymentSettings()->isRegistrationFeeConfigured())->toBeTrue()
        ->and(paymentSettings()->isBankTransferConfigured())->toBeFalse();

    paymentSettings()->setBankTransferInstructions('Pay here');

    expect(paymentSettings()->isBankTransferConfigured())->toBeTrue();
});

it('caches reads so repeated access does not re-query', function () {
    settingsRepository()->set(PaymentSettings::REGISTRATION_FEE_AMOUNT, '25.000');

    settingsRepository()->all();

    DB::enableQueryLog();

    settingsRepository()->get(PaymentSettings::REGISTRATION_FEE_AMOUNT);
    settingsRepository()->get(PaymentSettings::BANK_TRANSFER_INSTRUCTIONS);
    settingsRepository()->all();

    expect(DB::getQueryLog())->toBeEmpty();

    DB::disableQueryLog();
});

/**
 * Guards the reason the whole map is cached under one key rather than per key:
 * Cache::remember() treats NULL as a miss, so a per-key cache would re-query the database on
 * every check of an unconfigured setting — which is its steady state until configured.
 */
it('caches an unset value instead of re-querying it', function () {
    $this->seed(PaymentSettingsSeeder::class);
    settingsRepository()->flush();

    settingsRepository()->all();

    DB::enableQueryLog();

    expect(settingsRepository()->get(PaymentSettings::REGISTRATION_FEE_AMOUNT))->toBeNull()
        ->and(settingsRepository()->get(PaymentSettings::REGISTRATION_FEE_AMOUNT))->toBeNull()
        ->and(DB::getQueryLog())->toBeEmpty();

    DB::disableQueryLog();
});

it('invalidates the cache on write', function () {
    settingsRepository()->set(PaymentSettings::REGISTRATION_FEE_AMOUNT, '25.000');

    expect(settingsRepository()->get(PaymentSettings::REGISTRATION_FEE_AMOUNT))->toBe('25.000');

    settingsRepository()->set(PaymentSettings::REGISTRATION_FEE_AMOUNT, '30.000');

    expect(settingsRepository()->get(PaymentSettings::REGISTRATION_FEE_AMOUNT))->toBe('30.000');
});

it('invalidates the cache when the fee is changed through the typed accessor', function () {
    paymentSettings()->setRegistrationFee(OmrAmount::fromString('25.000'));

    expect(paymentSettings()->registrationFee()?->value)->toBe('25.000');

    paymentSettings()->setRegistrationFee(OmrAmount::fromString('30.000'));

    expect(paymentSettings()->registrationFee()?->value)->toBe('30.000');
});

/**
 * Proves the cache is real rather than the assertions above passing by accident: a write
 * that bypasses the repository is NOT observed until the cache is flushed.
 */
it('serves a stale value until flushed when the table is written behind its back', function () {
    settingsRepository()->set(PaymentSettings::REGISTRATION_FEE_AMOUNT, '25.000');

    expect(settingsRepository()->get(PaymentSettings::REGISTRATION_FEE_AMOUNT))->toBe('25.000');

    DB::table('settings')
        ->where('key', PaymentSettings::REGISTRATION_FEE_AMOUNT)
        ->update(['value' => '99.000']);

    expect(settingsRepository()->get(PaymentSettings::REGISTRATION_FEE_AMOUNT))->toBe('25.000');

    settingsRepository()->flush();

    expect(settingsRepository()->get(PaymentSettings::REGISTRATION_FEE_AMOUNT))->toBe('99.000');
});

it('distinguishes a key seeded as null from a key that is absent', function () {
    $this->seed(PaymentSettingsSeeder::class);
    settingsRepository()->flush();

    expect(settingsRepository()->all())->toHaveKey(PaymentSettings::REGISTRATION_FEE_AMOUNT)
        ->and(settingsRepository()->all()[PaymentSettings::REGISTRATION_FEE_AMOUNT])->toBeNull()
        ->and(settingsRepository()->all())->not->toHaveKey('some_absent_key')
        ->and(settingsRepository()->isConfigured(PaymentSettings::REGISTRATION_FEE_AMOUNT))->toBeFalse();
});
