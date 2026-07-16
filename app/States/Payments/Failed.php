<?php

declare(strict_types=1);

namespace App\States\Payments;

/**
 * The provider declined the payment, or a technical failure ended the attempt. No human
 * decided this — see Rejected for that.
 *
 * Terminal, but not the end of the road: the guardian may start a fresh attempt. The dead
 * attempt is kept so the history of what was tried survives.
 */
class Failed extends PaymentState
{
    public static string $name = 'failed';

    public function getLabel(): string
    {
        return __('admin.payment.states.failed');
    }

    public function getColor(): string
    {
        return 'danger';
    }
}
