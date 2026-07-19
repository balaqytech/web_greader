<?php

declare(strict_types=1);

namespace App\Events;

/**
 * An application was accepted under branch review. Carries stable ids, the public reference,
 * the branch, and the resulting state only — never guardian/student details or any free text.
 */
final readonly class ApplicationAccepted implements OutboxEvent
{
    public function __construct(
        public int $applicationId,
        public string $reference,
        public ?int $branchId,
    ) {}

    public function outboxEventType(): string
    {
        return 'application.accepted';
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
            'state' => 'accepted',
        ];
    }
}
