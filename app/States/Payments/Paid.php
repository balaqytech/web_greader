<?php

declare(strict_types=1);

namespace App\States\Payments;

/**
 * The fee is settled. Reaching this state is what advances the application past the fee
 * gate, and that advance happens inside the same transaction as the transition, so a
 * half-applied payment is impossible.
 *
 * Terminal. A Thawani payment only ever arrives here through server-side verification of the
 * checkout session — never from a browser redirect claiming success, which is client-
 * controlled and would otherwise let anyone mark their own fee paid by editing a URL.
 */
class Paid extends PaymentState
{
    public static string $name = 'paid';

    public function getLabel(): string
    {
        return __('admin.payment.states.paid');
    }

    public function getColor(): string
    {
        return 'success';
    }
}
