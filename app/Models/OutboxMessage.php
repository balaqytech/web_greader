<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A recorded domain lifecycle event awaiting (future) delivery. Written transactionally with
 * the state change that produced it. Phase 5 only records these rows — nothing consumes them
 * yet — so the status lifecycle is pending -> processed|failed for a later worker to drive.
 *
 * @property string $event_type
 * @property string $aggregate_type
 * @property string $aggregate_id
 * @property array<string, mixed> $payload
 * @property string $status
 * @property int $attempts
 * @property Carbon|null $processed_at
 */
class OutboxMessage extends Model
{
    public const StatusPending = 'pending';

    public const StatusProcessed = 'processed';

    public const StatusFailed = 'failed';

    protected $fillable = [
        'event_type',
        'aggregate_type',
        'aggregate_id',
        'payload',
        'status',
        'attempts',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'processed_at' => 'datetime',
        ];
    }
}
