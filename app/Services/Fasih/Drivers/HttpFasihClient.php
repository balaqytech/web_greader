<?php

declare(strict_types=1);

namespace App\Services\Fasih\Drivers;

use App\Services\Fasih\FasihClient;
use Spatie\WebhookServer\WebhookCall;

/**
 * The real Fasih transport: posts notifications over Spatie's WebhookServer. This is the ONLY
 * place a `WebhookCall`, an endpoint URL, or the signing secret lives.
 *
 * The lead-created notification is signed with HMAC-SHA256 when a secret is configured (and
 * only then); affiliate verification is always unsigned — preserving the prior contract. A
 * missing endpoint URL is treated as "nothing to send" rather than an error, so a half
 * configured deployment degrades quietly.
 */
final class HttpFasihClient implements FasihClient
{
    /**
     * @param  array<string, mixed>  $config  The `services.fasih` config array.
     */
    public function __construct(private array $config) {}

    public function leadCreated(array $payload): void
    {
        $url = $this->url('lead_created');

        if ($url === null) {
            return;
        }

        $call = $this->baseCall($url, $payload);

        $secret = $this->config['secret'] ?? null;

        if (is_string($secret) && $secret !== '') {
            $call->useSecret($secret);
        } else {
            $call->doNotSign();
        }

        $call->dispatch();
    }

    public function affiliateVerified(array $payload): void
    {
        $url = $this->url('affiliate_verified');

        if ($url === null) {
            return;
        }

        $this->baseCall($url, $payload)->doNotSign()->dispatch();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function baseCall(string $url, array $payload): WebhookCall
    {
        return WebhookCall::create()
            ->url($url)
            ->payload($payload)
            ->timeoutInSeconds((int) ($this->config['timeout'] ?? 10));
    }

    private function url(string $notification): ?string
    {
        $url = $this->config[$notification]['url'] ?? null;

        return is_string($url) && trim($url) !== '' ? $url : null;
    }
}
