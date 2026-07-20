<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiIdempotencyKey;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response as ResponseFactory;
use Illuminate\Support\Str;
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
        $ownerToken = (string) Str::uuid();

        $reservation = $this->reserve($tokenId, $rawKey, $requestHash, $ownerToken);

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
            $this->release($record, $ownerToken);

            throw $e;
        }

        $this->complete($record, $ownerToken, $response);

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
    private function reserve(?int $tokenId, string $key, string $requestHash, string $ownerToken): array
    {
        $existing = ApiIdempotencyKey::query()
            ->where('token_id', $tokenId)
            ->where('key', $key)
            ->first();

        if ($existing !== null) {
            return $this->resolveExisting($tokenId, $key, $requestHash, $ownerToken);
        }

        try {
            return [
                'status' => 'reserved',
                'record' => ApiIdempotencyKey::create([
                    'token_id' => $tokenId,
                    'key' => $key,
                    'request_hash' => $requestHash,
                    'owner_token' => $ownerToken,
                    'processing_at' => Carbon::now(),
                    'expires_at' => Carbon::now()->addSeconds(self::LeaseSeconds),
                ]),
            ];
        } catch (UniqueConstraintViolationException) {
            // Lost the race — another request reserved this key first.
            return $this->resolveExisting($tokenId, $key, $requestHash, $ownerToken);
        }
    }

    /**
     * @return array{status: string, record?: ApiIdempotencyKey, retry_after?: int}
     */
    private function resolveExisting(
        ?int $tokenId,
        string $key,
        string $requestHash,
        string $ownerToken,
    ): array {
        return DB::transaction(function () use ($tokenId, $key, $requestHash, $ownerToken): array {
            $existing = ApiIdempotencyKey::query()
                ->where('token_id', $tokenId)
                ->where('key', $key)
                ->lockForUpdate()
                ->first();

            if ($existing === null) {
                return $this->freshReservation($tokenId, $key, $requestHash, $ownerToken);
            }

            return $this->resolveLocked($existing, $requestHash, $ownerToken);
        }, attempts: 3);
    }

    /**
     * @return array{status: string, record?: ApiIdempotencyKey, retry_after?: int}
     */
    private function resolveLocked(ApiIdempotencyKey $existing, string $requestHash, string $ownerToken): array
    {
        if ($existing->isCompleted()) {
            if (! $existing->isLive()) {
                return $this->takeOver($existing, $requestHash, $ownerToken);
            }

            return hash_equals($existing->request_hash, $requestHash)
                ? ['status' => 'replay', 'record' => $existing]
                : ['status' => 'conflict'];
        }

        if ($existing->isLive()) {
            return [
                'status' => 'in_progress',
                'retry_after' => max(1, (int) round($existing->expires_at->diffInSeconds(Carbon::now(), true))),
            ];
        }

        return $this->takeOver($existing, $requestHash, $ownerToken);
    }

    /**
     * @return array{status: 'reserved', record: ApiIdempotencyKey}
     */
    private function takeOver(ApiIdempotencyKey $existing, string $requestHash, string $ownerToken): array
    {
        $existing->update([
            'request_hash' => $requestHash,
            'owner_token' => $ownerToken,
            'response_status' => null,
            'response_body' => null,
            'processing_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addSeconds(self::LeaseSeconds),
        ]);

        return ['status' => 'reserved', 'record' => $existing->refresh()];
    }

    /**
     * @return array{status: string, record?: ApiIdempotencyKey, retry_after?: int}
     */
    private function freshReservation(?int $tokenId, string $key, string $requestHash, string $ownerToken): array
    {
        try {
            return [
                'status' => 'reserved',
                'record' => ApiIdempotencyKey::create([
                    'token_id' => $tokenId,
                    'key' => $key,
                    'request_hash' => $requestHash,
                    'owner_token' => $ownerToken,
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
    private function complete(ApiIdempotencyKey $record, string $ownerToken, Response $response): void
    {
        $owned = ApiIdempotencyKey::query()
            ->whereKey($record->getKey())
            ->where('owner_token', $ownerToken);

        if ($response->getStatusCode() >= 500) {
            $owned->delete();

            return;
        }

        $owned->update([
            'response_status' => $response->getStatusCode(),
            'response_body' => $response->getContent() === false ? null : $response->getContent(),
            'owner_token' => null,
            'processing_at' => null,
            'expires_at' => Carbon::now()->addSeconds(self::CompletedTtlSeconds),
        ]);
    }

    private function release(ApiIdempotencyKey $record, string $ownerToken): void
    {
        ApiIdempotencyKey::query()
            ->whereKey($record->getKey())
            ->where('owner_token', $ownerToken)
            ->delete();
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
