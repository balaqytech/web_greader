<?php

declare(strict_types=1);

namespace App\States\Payments;

/**
 * A bank-transfer receipt has been uploaded and is waiting on central finance to verify or
 * reject it. Bank transfers only — no other method passes through here.
 */
class AwaitingVerification extends PaymentState
{
    public static string $name = 'awaiting_verification';

    public function getLabel(): string
    {
        return __('admin.payment.states.awaiting_verification');
    }

    public function getColor(): string
    {
        return 'warning';
    }
}
