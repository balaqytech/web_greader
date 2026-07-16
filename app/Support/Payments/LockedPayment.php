<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Models\Application;
use App\Models\Payment;

/**
 * A payment and its application, both freshly re-read under `FOR UPDATE` in the mandatory
 * lock order. Only `LockPayment` constructs this, so holding one is evidence the rows are
 * genuinely locked and their persisted states were verified.
 */
final readonly class LockedPayment
{
    public function __construct(
        public Application $application,
        public Payment $payment,
    ) {}
}
