<?php

declare(strict_types=1);

namespace App\Support\Payments;

/**
 * A single row of `PaymentApplicationProjection`. Deliberately not an Eloquent model and not a
 * wrapper around one — it carries exactly the fields central finance is allowed to see and
 * nothing an unscoped `Application` model would otherwise expose (guardian contact details,
 * civil numbers, documents, ...).
 */
final readonly class PaymentApplicationProjectionRow
{
    public function __construct(
        public string $paymentReference,
        public string $applicationReference,
        public string $studentName,
        public string $programName,
        public string $branchName,
        public string $feeAmount,
        public string $currency,
    ) {}
}
