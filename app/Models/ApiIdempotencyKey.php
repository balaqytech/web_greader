<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A single idempotency reservation/record for a mutating service-API request.
 *
 * Lifecycle: created as a *reservation* (response_status null, expires_at = now + processing
 * lease) then *completed* (response_status/body stored, expires_at = now + replay TTL). Rows
 * are mass-prunable once expired — abandoned leases and lapsed replays are removed the same way.
 *
 * @property int|null $token_id
 * @property string $key
 * @property string $request_hash
 * @property string|null $owner_token
 * @property int|null $response_status
 * @property string|null $response_body
 * @property Carbon|null $processing_at
 * @property Carbon $expires_at
 */
class ApiIdempotencyKey extends Model
{
    use MassPrunable;

    protected $fillable = [
        'token_id',
        'key',
        'request_hash',
        'owner_token',
        'response_status',
        'response_body',
        'processing_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'processing_at' => 'datetime',
            'expires_at' => 'datetime',
            'response_status' => 'integer',
        ];
    }

    /**
     * A record is a completed, replayable response once a status has been stored.
     */
    public function isCompleted(): bool
    {
        return $this->response_status !== null;
    }

    /**
     * Whether the current lease/replay window is still valid.
     */
    public function isLive(): bool
    {
        return $this->expires_at->isFuture();
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('expires_at', '<', now());
    }
}
