<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Payments\ResolvePaymentFromProviderAction;
use App\Enums\PaymentMethod;
use App\Exceptions\PaymentGatewayException;
use App\Models\Payment;
use App\Models\Scopes\BranchScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Asks the provider about pending Thawani attempts that have gone quiet, and settles them.
 *
 * **This is not a cleanup job — it is how payments settle.** There is no webhook in this
 * phase, so the browser return is the only push we get, and it only happens if the guardian
 * actually comes back. Someone who pays and then closes the tab, loses signal, or gets a
 * failed redirect would otherwise sit unpaid forever with money taken. This command is what
 * makes that impossible, so it must be scheduled in every environment that takes payments.
 *
 * It also recovers attempts whose initiation timed out after the provider had already created
 * the session: those have no stored session id, and `ResolvePaymentFromProviderAction` finds
 * them by client reference instead.
 *
 * Runs unauthenticated (console), so BranchScope is inert — but the scope is bypassed
 * explicitly rather than relying on that, since a future authenticated caller must not
 * silently reconcile only its own branch.
 */
class ReconcilePendingPaymentsCommand extends Command
{
    protected $signature = 'payments:reconcile
                            {--limit=100 : Maximum attempts to examine in one run}
                            {--stale-minutes= : Override how long an attempt must be quiet first}';

    protected $description = 'Ask the provider about quiet pending Thawani payments and settle them. Without a webhook, this is how payments completed after the guardian left are settled.';

    public function handle(ResolvePaymentFromProviderAction $resolve): int
    {
        $staleMinutes = (int) ($this->option('stale-minutes') ?? config('payments.reconciliation.stale_after_minutes', 15));

        $payments = Payment::withoutGlobalScope(BranchScope::class)
            ->where('method', PaymentMethod::THAWANI)
            ->forRegistrationFee()
            ->active()
            // Only attempts that have gone quiet: a checkout the guardian is on right now
            // would just come back UNPAID and waste a provider call.
            ->where('updated_at', '<=', now()->subMinutes($staleMinutes))
            ->orderBy('updated_at')
            ->limit((int) $this->option('limit'))
            ->get();

        if ($payments->isEmpty()) {
            $this->info('No pending payments are due for reconciliation.');

            return self::SUCCESS;
        }

        $settled = 0;
        $unresolved = 0;

        foreach ($payments as $payment) {
            try {
                $resolved = $resolve->execute($payment);

                if ($resolved->status->isTerminal()) {
                    $settled++;
                    $this->line("Settled {$resolved->reference} as {$resolved->status::$name}.");

                    continue;
                }

                $unresolved++;
            } catch (PaymentGatewayException $e) {
                // One unreachable provider must not abort the whole run: the next attempt
                // may be for a session that genuinely needs settling, and a partial pass is
                // better than none. Nothing is concluded about this attempt.
                $unresolved++;

                Log::warning('Could not reconcile a pending payment; it stays pending and will be retried on the next run.', [
                    'payment_reference' => $payment->reference,
                    'reason' => $e->getMessage(),
                    'retryable' => $e->retryable,
                ]);
            }
        }

        $this->info("Reconciled {$payments->count()} attempt(s): {$settled} settled, {$unresolved} still unresolved.");

        return self::SUCCESS;
    }
}
