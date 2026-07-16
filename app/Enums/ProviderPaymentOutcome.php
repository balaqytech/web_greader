<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What the provider says about a checkout session, normalised into this domain's own terms.
 *
 * Deliberately not the provider's own vocabulary: the adapter maps into this enum so the
 * payment domain never learns Thawani's status strings, and swapping providers cannot ripple
 * past the adapter boundary.
 *
 * `UNKNOWN` exists so an unrecognised provider status has somewhere safe to land. An
 * unmapped status must never be guessed into PAID or FAILED — it leaves the attempt pending
 * and retryable, which is the only direction that cannot lose money or invent it.
 */
enum ProviderPaymentOutcome: string
{
    /** The provider confirms the money was captured. The only outcome that settles a fee. */
    case PAID = 'paid';

    /** The session exists and is still open; nothing has been decided yet. */
    case UNPAID = 'unpaid';

    /** The session was cancelled — by the guardian, or by us. */
    case CANCELLED = 'cancelled';

    /** The session's window closed before it was completed. Nothing was declined. */
    case EXPIRED = 'expired';

    /** The provider explicitly declined the payment. */
    case FAILED = 'failed';

    /** The provider returned a status this adapter does not recognise. Stay pending. */
    case UNKNOWN = 'unknown';

    /**
     * Whether this outcome settles the attempt for good. UNPAID and UNKNOWN do not: both mean
     * "ask again later" rather than "this is over".
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::PAID, self::CANCELLED, self::EXPIRED, self::FAILED => true,
            self::UNPAID, self::UNKNOWN => false,
        };
    }
}
