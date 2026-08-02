<?php

use App\Models\Setting;
use App\Models\User;
use App\Support\Settings\SettingsRepository;
use Illuminate\Support\Facades\Schema;
use OwenIt\Auditing\Models\Audit;

it('stores and resolves audits for string-keyed settings', function () {
    config(['audit.console' => true]);

    $this->actingAs(User::factory()->create());

    $settings = app(SettingsRepository::class);
    $settings->set('registration_fee_amount', '50.000');
    $settings->set('registration_fee_amount', '60.000');

    $setting = Setting::query()->findOrFail('registration_fee_amount');
    $audits = Audit::query()
        ->where('auditable_type', $setting->getMorphClass())
        ->where('auditable_id', $setting->getKey())
        ->oldest('id')
        ->get();

    expect(Schema::getColumnType('audits', 'auditable_id'))->toBe('varchar')
        ->and($audits)->toHaveCount(2)
        ->and($audits->pluck('event')->all())->toBe(['created', 'updated'])
        ->and($audits->first()->auditable_id)->toBe('registration_fee_amount')
        ->and($audits->first()->auditable)->toBeInstanceOf(Setting::class)
        ->and($audits->first()->auditable->is($setting))->toBeTrue()
        ->and($audits->last()->old_values)->toBe(['value' => '50.000'])
        ->and($audits->last()->new_values)->toBe(['value' => '60.000']);
});
