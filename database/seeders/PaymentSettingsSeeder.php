<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use App\Support\Settings\PaymentSettings;
use App\Support\Settings\SettingsRepository;
use Illuminate\Database\Seeder;

/**
 * Declares the payment settings keys so they are discoverable in the settings UI before an
 * administrator has configured them.
 *
 * Every key is seeded with a NULL value on purpose — "declared but not configured". There is
 * no default registration fee: a guessed or zero default would silently let applications
 * through the fee gate for free.
 *
 * `firstOrCreate`, never `updateOrCreate`: re-running this seeder against an environment
 * where the fee has already been configured must never reset it back to NULL.
 */
class PaymentSettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PaymentSettings::KEYS as $key) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => null]);
        }

        app(SettingsRepository::class)->flush();

        $this->command?->info('Payment settings keys seeded (unset).');
    }
}
