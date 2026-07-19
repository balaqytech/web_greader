<?php

declare(strict_types=1);

namespace App\Events;

/**
 * An application was rejected under branch review. The rejection reason is deliberately NOT
 * included — only the ids, public reference, branch, and resulting state.
 */
final readonly class ApplicationRejected implements OutboxEvent
{
    public function __construct(
        public int $applicationId,
        public string $reference,
        public ?int $branchId,
    ) {}

    public function outboxEventType(): string
    {
        return 'application.rejected';
    }

    public function outboxAggregateType(): string
    {
        return 'application';
    }

    public function outboxAggregateId(): string
    {
        return (string) $this->applicationId;
    }

    public function outboxPayload(): array
    {
        return [
            'application_id' => $this->applicationId,
            'reference' => $this->reference,
            'branch_id' => $this->branchId,
            'state' => 'rejected',
        ];
    }
}
