<?php

declare(strict_types=1);

namespace App\Events;

/**
 * A new immutable contract version was generated. Carries the contract id and version, the
 * owning application ids/reference, and the branch — never the contract token, rendered body,
 * or template hash.
 */
final readonly class ContractGenerated implements OutboxEvent
{
    public function __construct(
        public int $contractId,
        public int $applicationId,
        public ?string $applicationReference,
        public ?int $branchId,
        public int $version,
    ) {}

    public function outboxEventType(): string
    {
        return 'contract.generated';
    }

    public function outboxAggregateType(): string
    {
        return 'contract';
    }

    public function outboxAggregateId(): string
    {
        return (string) $this->contractId;
    }

    public function outboxPayload(): array
    {
        return [
            'contract_id' => $this->contractId,
            'application_id' => $this->applicationId,
            'application_reference' => $this->applicationReference,
            'branch_id' => $this->branchId,
            'version' => $this->version,
        ];
    }
}
