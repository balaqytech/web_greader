<?php

declare(strict_types=1);

namespace App\Events;

/**
 * A contract version was signed — by the applicant online or via a staff upload (the
 * `signedByApplicant` flag distinguishes them). Carries the contract id/version, the owning
 * application ids/reference, and the branch — never the signed artifact path or signature.
 */
final readonly class ContractSigned implements OutboxEvent
{
    public function __construct(
        public int $contractId,
        public int $applicationId,
        public ?string $applicationReference,
        public ?int $branchId,
        public int $version,
        public bool $signedByApplicant,
    ) {}

    public function outboxEventType(): string
    {
        return 'contract.signed';
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
            'signed_by_applicant' => $this->signedByApplicant,
        ];
    }
}
