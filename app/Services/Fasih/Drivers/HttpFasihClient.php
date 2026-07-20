<?php

declare(strict_types=1);

namespace App\Services\Fasih\Drivers;

use App\Services\Fasih\FasihClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The real Fasih transport. This is the only place an endpoint URL, signing secret, or HTTP
 * transport detail lives.
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

        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $this->request($body, signed: true)
            ->withBody($body, 'application/json')
            ->post($url)
            ->throw();
    }

    public function affiliateVerified(array $payload): void
    {
        $url = $this->url('affiliate_verified');

        if ($url === null) {
            return;
        }

        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $this->request($body, signed: false)
            ->withBody($body, 'application/json')
            ->post($url)
            ->throw();
    }

    private function request(string $body, bool $signed): PendingRequest
    {
        $request = Http::acceptJson()
            ->connectTimeout(max(1, (int) ($this->config['connect_timeout'] ?? 5)))
            ->timeout(max(1, (int) ($this->config['timeout'] ?? 10)))
            ->retry(
                [250, 1000],
                when: fn (Throwable $exception): bool => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException
                        && ($exception->response->status() === 429 || $exception->response->serverError())),
            );

        if (! $signed) {
            return $request;
        }

        $secret = $this->config['secret'] ?? null;

        if (! is_string($secret) || $secret === '') {
            return $request;
        }

        $signature = hash_hmac(
            'sha256',
            $body,
            $secret,
        );

        return $request->withHeader($this->signatureHeaderName(), $signature);
    }

    private function signatureHeaderName(): string
    {
        $header = config('webhook-server.signature_header_name', 'Signature');

        return is_string($header) && $header !== '' ? $header : 'Signature';
    }

    private function url(string $notification): ?string
    {
        $url = $this->config[$notification]['url'] ?? null;

        return is_string($url) && trim($url) !== '' ? $url : null;
    }
}
