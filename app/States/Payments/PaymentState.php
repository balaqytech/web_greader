<?php

declare(strict_types=1);

namespace App\States\Payments;

use App\States\Payments\Transitions\PendingToExpired;
use App\States\Payments\Transitions\PendingToFailed;
use App\States\Payments\Transitions\PendingToPaid;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * Registration-fee payment lifecycle.
 *
 *   [*]                  -> Pending              attempt created
 *   Pending              -> Paid                 provider-verified, or manually confirmed
 *   Pending              -> Failed               provider decline or technical failure
 *   Pending              -> Expired              checkout session expired
 *   Pending              -> AwaitingVerification bank receipt uploaded
 *   AwaitingVerification -> Paid                 central finance verifies the receipt
 *   AwaitingVerification -> Rejected             central finance rejects the receipt (reason)
 *
 * `Failed` and `Rejected` are deliberately distinct: `Failed` is a provider decline or a
 * technical error with no human involved, `Rejected` is a finance officer deciding against a
 * bank receipt and must carry a reason. Collapsing them would lose the difference between
 * "the gateway said no" and "a person said no", which is exactly what a disputing guardian
 * needs answered.
 *
 * Paid, Failed, Rejected and Expired are terminal — no edge leaves them. Refunds are out of
 * scope for this domain, so there is no refunded state and `Paid` really is final here.
 *
 * As with ApplicationState, only transitions whose classes are fully supported end-to-end are
 * registered; each edge is registered by the phase that supplies its side effects (provider
 * verification, bank verification, cash confirmation). An unregistered edge is refused by
 * Spatie rather than silently allowed, so a half-built edge cannot be reached.
 */
abstract class PaymentState extends State implements HasColor, HasLabel
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Pending::class)
            ->allowTransition(Pending::class, Paid::class, PendingToPaid::class)
            ->allowTransition(Pending::class, Failed::class, PendingToFailed::class)
            ->allowTransition(Pending::class, Expired::class, PendingToExpired::class);
    }

    /**
     * The states an attempt can still leave — and therefore the ones that block a second
     * concurrent attempt on the same application.
     *
     * Declared once here and reused by both `isActive()` and the model's query scopes, so the
     * in-memory rule and the SQL rule can never drift apart.
     *
     * @return list<class-string<PaymentState>>
     */
    public static function activeStates(): array
    {
        return [Pending::class, AwaitingVerification::class];
    }

    /**
     * @return list<class-string<PaymentState>>
     */
    public static function terminalStates(): array
    {
        return [Paid::class, Failed::class, Rejected::class, Expired::class];
    }

    /**
     * A terminal state never transitions again. Retrying a payment creates a *new* attempt
     * rather than reviving a dead one, so the history of what was tried stays intact.
     */
    public function isTerminal(): bool
    {
        return ! $this->isActive();
    }

    public function isActive(): bool
    {
        foreach (static::activeStates() as $state) {
            if ($this instanceof $state) {
                return true;
            }
        }

        return false;
    }
}
