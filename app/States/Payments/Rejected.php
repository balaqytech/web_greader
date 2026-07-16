<?php

declare(strict_types=1);

namespace App\States\Payments;

/**
 * Central finance reviewed an uploaded bank-transfer receipt and decided against it. A
 * deliberate human decision — always carries a reason, so the guardian can be told what was
 * wrong and what to do about it.
 *
 * Terminal; a corrected receipt is a fresh attempt, leaving this one intact as history.
 */
class Rejected extends PaymentState
{
    public static string $name = 'rejected';

    public function getLabel(): string
    {
        return __('admin.payment.states.rejected');
    }

    public function getColor(): string
    {
        return 'danger';
    }
}
