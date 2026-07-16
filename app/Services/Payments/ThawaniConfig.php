<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Exceptions\PaymentGatewayException;

/**
 * Validated Thawani credentials. Constructing one is proof the configuration is usable, so
 * the adapter checks configuration once, up front, rather than discovering a missing key as
 * a confusing 401 midway through a payment.
 *
 * The bundled-default guard is the reason this class earns its keep. `jkbroot/thawani` ships
 * a REAL UAT secret key as the default value of `test.secret_key`, and merges its config in
 * whether or not it was published. `config/thawani.php` in this repository overrides those
 * defaults with env-only values — but if that file is ever deleted, renamed, or lost in a
 * merge, the package's defaults quietly return and the application would transact against a
 * stranger's UAT merchant. That failure is invisible: requests succeed, checkout pages
 * render, and the money simply is not ours. So the known bundled values are rejected by
 * value here, as a backstop that does not depend on a config file existing.
 */
final readonly class ThawaniConfig
{
    /**
     * The credentials `jkbroot/thawani` hardcodes as defaults in its own config file. Not
     * secrets — they are published in the package's public source — and listed here only so
     * they can be refused.
     *
     * @var list<string>
     */
    private const BUNDLED_PACKAGE_DEFAULTS = [
        'rRQ26GcsZzoEhbrP2HZvLYDbn9C9et',
        'HGvTMLDssJghr9tlN9gr4DVYt0qyBy',
    ];

    /**
     * @var list<string>
     */
    private const SUPPORTED_MODES = ['test', 'live'];

    private function __construct(
        public string $mode,
        public string $baseUrl,
        public string $checkoutBaseUrl,
        public string $secretKey,
        public string $publishableKey,
    ) {}

    /**
     * @throws PaymentGatewayException
     */
    public static function fromConfig(): self
    {
        $mode = config('thawani.mode');

        if (! is_string($mode) || ! in_array($mode, self::SUPPORTED_MODES, true)) {
            throw PaymentGatewayException::misconfigured(sprintf(
                'THAWANI_MODE must be one of %s.',
                implode(', ', self::SUPPORTED_MODES)
            ));
        }

        $credentials = config("thawani.{$mode}");

        if (! is_array($credentials)) {
            throw PaymentGatewayException::misconfigured("no configuration exists for mode [{$mode}].");
        }

        $values = [];

        foreach (['base_url', 'checkout_base_url', 'secret_key', 'publishable_key'] as $key) {
            $value = $credentials[$key] ?? null;

            if (! is_string($value) || trim($value) === '') {
                throw PaymentGatewayException::misconfigured(
                    "[{$key}] is missing for mode [{$mode}]. Set the corresponding THAWANI_* environment variable; there is no default."
                );
            }

            $values[$key] = $value;
        }

        foreach (['secret_key', 'publishable_key'] as $key) {
            if (in_array($values[$key], self::BUNDLED_PACKAGE_DEFAULTS, true)) {
                throw PaymentGatewayException::misconfigured(
                    "[{$key}] is the default value bundled inside the jkbroot/thawani package, which belongs to someone else's merchant account. "
                    .'Set a real THAWANI_* environment variable, and check that config/thawani.php still exists.'
                );
            }
        }

        return new self(
            mode: $mode,
            baseUrl: $values['base_url'],
            checkoutBaseUrl: $values['checkout_base_url'],
            secretKey: $values['secret_key'],
            publishableKey: $values['publishable_key'],
        );
    }

    public function isLive(): bool
    {
        return $this->mode === 'live';
    }
}
