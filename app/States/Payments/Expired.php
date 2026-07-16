<?php

declare(strict_types=1);

namespace App\States\Payments;

/**
 * The provider's checkout session expired before it was completed. Distinct from Failed:
 * nothing was declined, the window simply closed.
 *
 * Terminal; the guardian may start a fresh attempt.
 */
class Expired extends PaymentState
{
    public static string $name = 'expired';

    public function getLabel(): string
    {
        return __('admin.payment.states.expired');
    }

    public function getColor(): string
    {
        return 'warning';
    }
}
