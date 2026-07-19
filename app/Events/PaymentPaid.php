<?php

declare(strict_types=1);

namespace App\Events;

/**
 * A registration-fee attempt settled as the paid winner. Dispatched only for the attempt that
 * actually reaches Paid — never for a second charge that was detected and moved to Failed.
 * Carries the payment method and amount, ids, and public references only — no provider payload.
 */
final readonly class PaymentPaid implements OutboxEvent
{
    public function __construct(
        public int $paymentId,
        public string $paymentReference,
        public int $applicationId,
        public ?string $applicationReference,
        public ?int $branchId,
        public string $method,
        public string $amount,
    ) {}

    public function outboxEventType(): string
    {
        return 'payment.paid';
    }

    public function outboxAggregateType(): string
    {
        return 'payment';
    }

    public function outboxAggregateId(): string
    {
        return (string) $this->paymentId;
    }

    public function outboxPayload(): array
    {
        return [
            'payment_id' => $this->paymentId,
            'payment_reference' => $this->paymentReference,
            'application_id' => $this->applicationId,
            'application_reference' => $this->applicationReference,
            'branch_id' => $this->branchId,
            'method' => $this->method,
            'amount' => $this->amount,
        ];
    }
}
