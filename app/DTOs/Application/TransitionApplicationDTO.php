<?php

namespace App\DTOs\Application;

final readonly class TransitionApplicationDTO
{
    public function __construct(
        public string $targetState,
        public ?int $transitionedBy = null,
        public ?string $notes = null,
        public ?string $rejectionReason = null,
    ) {}

    public static function fromValidated(array $data): self
    {
        return new self(
            targetState: $data['target_state'],
            transitionedBy: $data['transitioned_by'] ?? null,
            notes: $data['notes'] ?? null,
            rejectionReason: $data['rejection_reason'] ?? null,
        );
    }
}
