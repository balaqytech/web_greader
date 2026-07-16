<?php

declare(strict_types=1);

namespace App\States\Payments;

/**
 * An attempt has been created but no money is confirmed. A Thawani checkout session may be
 * open, a bank transfer may be awaiting its receipt, or a cash payment may be awaiting
 * in-person confirmation.
 *
 * A network error or timeout while talking to the provider leaves an attempt here rather
 * than failing it: the provider may still have created the session, so the attempt stays
 * retryable and is resolved by asking the provider, never by assuming.
 */
class Pending extends PaymentState
{
    public static string $name = 'pending';

    public function getLabel(): string
    {
        return __('admin.payment.states.pending');
    }

    public function getColor(): string
    {
        return 'gray';
    }
}
