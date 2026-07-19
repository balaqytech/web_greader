<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OutboxEvent;
use App\Models\OutboxMessage;

/**
 * Writes a pending {@see OutboxMessage} for any {@see OutboxEvent}.
 *
 * This listener is intentionally synchronous and NOT queued, and the events it handles do NOT
 * implement ShouldDispatchAfterCommit: the row must be inserted inside the same transaction as
 * the domain state change, so the event record and the fact it describes commit — or roll back
 * — together. There is no delivery here; Phase 5 only records.
 */
class RecordOutboxMessage
{
    public function handle(OutboxEvent $event): void
    {
        OutboxMessage::create([
            'event_type' => $event->outboxEventType(),
            'aggregate_type' => $event->outboxAggregateType(),
            'aggregate_id' => $event->outboxAggregateId(),
            'payload' => $event->outboxPayload(),
            'status' => OutboxMessage::StatusPending,
            'attempts' => 0,
        ]);
    }
}
