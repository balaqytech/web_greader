<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiIdempotencyKey;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Response as ResponseFactory;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * At-most-once processing for mutating service-API requests, keyed on the `Idempotency-Key`
 * header scoped to the caller's token.
 *
 * A first request atomically reserves the key (a short processing lease) and runs the
 * controller; on a clean return the response status/body are stored for a 24h replay window.
 * A byte-identical retry replays the stored response; a retry with a *different* request under
 * the same key is a 409 conflict; a retry while the first is still in-flight is a 409 with
 * `Retry-After`. An uncaught exception releases the reservation so the operation can be retried.
 *
 * This is a supplement to — never a replacement for — the Phase 2 payment-row idempotency, which
 * remains the domain-level backstop.
 */
class EnforceApiIdempotency
{
    /** Processing lease: how long a reservation is considered in-flight before it is abandoned. */
    private const LeaseSeconds = 300;

    /** Replay window: how long a completed response is served back on an identical retry. */
    private const CompletedTtlSeconds = 86400;

    public function handle(Request $request, Closure $next): Response
    {
        $rawKey = $request->header('Idempotency-Key');

        if (! is_string($rawKey) || ($length = mb_strlen($rawKey)) < 1 || $length > 128) {
            return $this->conflict('idempotency_key_required', __('alerts.api.idempotency_key_required'));
        }

        $tokenId = $request->user('sanctum')?->currentAccessToken()?->getKey();
        $requestHash = $this->hashRequest($request);

        $reservation = $this->reserve($tokenId, $rawKey, $requestHash);

        if ($reservation['status'] === 'conflict') {
            return $this->conflict('idempotency_conflict', __('alerts.api.idempotency_key_conflict'));
        }

        if ($reservation['status'] === 'in_progress') {
            return $this->conflict('idempotency_in_progress', __('alerts.api.idempotency_in_progress'))
                ->header('Retry-After', (string) $reservation['retry_after']);
        }

        if ($reservation['status'] === 'replay') {
            return $this->replay($reservation['record']);
        }

        /** @var ApiIdempotencyKey $record */
        $record = $reservation['record'];

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            // The operation did not complete — free the reservation so a retry can proceed.
            $record->delete();

            throw $e;
        }

        $this->complete($record, $response);

        return $response;
    }

    /**
     * Atomically claim the key. Returns one of:
     *   reserved     — we own a fresh reservation and must run the controller
     *   replay       — a completed, identical response exists; serve it
     *   conflict     — the key was used for a different request
     *   in_progress  — the key is currently being processed elsewhere
     *
     * @return array{status: string, record?: ApiIdempotencyKey, retry_after?: int}
     */
    private function reserve(?int $tokenId, string $key, string $requestHash): array
    {
        $existing = ApiIdempotencyKey::query()
            ->where('token_id', $tokenId)
            ->where('key', $key)
            ->first();

        if ($existing !== null) {
            return $this->resolveExisting($existing, $requestHash);
        }

        try {
            return [
                'status' => 'reserved',
                'record' => ApiIdempotencyKey::create([
                    'token_id' => $tokenId,
                    'key' => $key,
                    'request_hash' => $requestHash,
                    'processing_at' => Carbon::now(),
                    'expires_at' => Carbon::now()->addSeconds(self::LeaseSeconds),
                ]),
            ];
        } catch (UniqueConstraintViolationException) {
            // Lost the race — another request reserved this key first.
            $existing = ApiIdempotencyKey::query()
                ->where('token_id', $tokenId)
                ->where('key', $key)
                ->first();

            if ($existing === null) {
                return ['status' => 'in_progress', 'retry_after' => self::LeaseSeconds];
            }

            return $this->resolveExisting($existing, $requestHash);
        }
    }

    /**
     * @return array{status: string, record?: ApiIdempotencyKey, retry_after?: int}
     */
    private function resolveExisting(ApiIdempotencyKey $existing, string $requestHash): array
    {
        // Completed record: a byte-identical retry replays it; a different request under the
        // same key is a conflict; a lapsed replay window is reprocessed.
        if ($existing->isCompleted()) {
            if (! $existing->isLive()) {
                $existing->delete();

                return $this->freshReservation($existing->token_id, $existing->key, $requestHash);
            }

            return hash_equals($existing->request_hash, $requestHash)
                ? ['status' => 'replay', 'record' => $existing]
                : ['status' => 'conflict'];
        }

        // Still-processing reservation whose lease is live: the first attempt is in flight, so
        // any retry (identical or not) must back off rather than double-process.
        if ($existing->isLive()) {
            return [
                'status' => 'in_progress',
                'retry_after' => max(1, (int) round($existing->expires_at->diffInSeconds(Carbon::now(), true))),
            ];
        }

        // The prior reservation's lease has expired without completing (a crashed request) — the
        // key is effectively free, so take it over for this request.
        $existing->update([
            'request_hash' => $requestHash,
            'response_status' => null,
            'response_body' => null,
            'processing_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addSeconds(self::LeaseSeconds),
        ]);

        return ['status' => 'reserved', 'record' => $existing];
    }

    /**
     * @return array{status: string, record?: ApiIdempotencyKey, retry_after?: int}
     */
    private function freshReservation(?int $tokenId, string $key, string $requestHash): array
    {
        try {
            return [
                'status' => 'reserved',
                'record' => ApiIdempotencyKey::create([
                    'token_id' => $tokenId,
                    'key' => $key,
                    'request_hash' => $requestHash,
                    'processing_at' => Carbon::now(),
                    'expires_at' => Carbon::now()->addSeconds(self::LeaseSeconds),
                ]),
            ];
        } catch (UniqueConstraintViolationException) {
            return ['status' => 'in_progress', 'retry_after' => self::LeaseSeconds];
        }
    }

    /**
     * Persist a completed response so an identical retry can replay it. Server errors (>= 500)
     * are treated as non-final and left un-cached so the caller can retry.
     */
    private function complete(ApiIdempotencyKey $record, Response $response): void
    {
        if ($response->getStatusCode() >= 500) {
            $record->delete();

            return;
        }

        $record->update([
            'response_status' => $response->getStatusCode(),
            'response_body' => $response->getContent() === false ? null : $response->getContent(),
            'processing_at' => null,
            'expires_at' => Carbon::now()->addSeconds(self::CompletedTtlSeconds),
        ]);
    }

    private function replay(ApiIdempotencyKey $record): Response
    {
        return ResponseFactory::make(
            $record->response_body ?? '',
            $record->response_status ?? Response::HTTP_OK,
            [
                'Content-Type' => 'application/json',
                'Idempotent-Replayed' => 'true',
            ],
        );
    }

    /**
     * Deterministic hash of the request identity: method, path, sorted query, sorted body, and
     * the SHA-256 of each uploaded file's *content* (never its temporary path, which changes
     * every request).
     */
    private function hashRequest(Request $request): string
    {
        $files = [];

        foreach ($request->allFiles() as $field => $file) {
            $files[$field] = $this->hashFiles($file);
        }

        ksort($files);

        $payload = [
            'method' => $request->getMethod(),
            'path' => $request->path(),
            'query' => $this->normalize($request->query->all()),
            'body' => $this->normalize($request->except(array_keys($request->allFiles()))),
            'files' => $files,
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  UploadedFile|array<int, UploadedFile>  $file
     * @return string|array<int, string>
     */
    private function hashFiles(mixed $file): string|array
    {
        if (is_array($file)) {
            return array_map(fn ($single) => $this->hashFiles($single), $file);
        }

        $path = $file->getRealPath();

        return is_string($path) && $path !== '' ? hash_file('sha256', $path) : '';
    }

    /**
     * Recursively sort array keys so semantically identical payloads hash identically
     * regardless of key order.
     *
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private function normalize(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->normalize($value);
            }
        }

        ksort($data);

        return $data;
    }

    private function conflict(string $error, string $message): Response
    {
        return ResponseFactory::json([
            'error' => $error,
            'message' => $message,
        ], Response::HTTP_CONFLICT);
    }
}
