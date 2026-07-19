<?php

declare(strict_types=1);

namespace App\Events;

use App\Listeners\RecordOutboxMessage;

/**
 * A domain lifecycle event that is recorded in the transactional outbox. Implementations are
 * scalar and immutable — they carry stable identifiers and public references only, never PII,
 * free text (rejection/correction reasons), contract tokens, artifact paths, or provider
 * payloads. The single {@see RecordOutboxMessage} listener turns any of these
 * into an `outbox_messages` row inside the dispatching transaction.
 */
interface OutboxEvent
{
    /**
     * Stable, dotted event type, e.g. `application.accepted`.
     */
    public function outboxEventType(): string;

    /**
     * The aggregate root type this event concerns, e.g. `application`.
     */
    public function outboxAggregateType(): string;

    /**
     * The aggregate root's stable identifier.
     */
    public function outboxAggregateId(): string;

    /**
     * @return array<string, mixed>
     */
    public function outboxPayload(): array;
}
