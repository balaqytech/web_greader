<?php

declare(strict_types=1);

namespace App\Services\Fasih;

/**
 * The application's whole view of the Fasih (chatbot) notification channel.
 *
 * Callers depend only on this contract, never on HTTP details or endpoint URLs. That keeps
 * endpoints, HMAC signing, retries, and timeouts behind the adapter so the provider can change
 * without touching the domain.
 */
interface FasihClient
{
    /**
     * Notify Fasih that a lead was created. Signed (HMAC-SHA256) when a secret is configured.
     *
     * @param  array<string, mixed>  $payload
     */
    public function leadCreated(array $payload): void;

    /**
     * Notify Fasih that an affiliate was verified. Always unsigned.
     *
     * @param  array<string, mixed>  $payload
     */
    public function affiliateVerified(array $payload): void;
}
