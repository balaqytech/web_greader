<?php

declare(strict_types=1);

namespace App\Console\Commands\Testing;

use App\Actions\Payments\ResolvePaymentFromProviderAction;
use App\DTOs\Payments\CheckoutRequestDTO;
use App\DTOs\Payments\CheckoutSessionDTO;
use App\DTOs\Payments\ProviderSessionStatusDTO;
use App\Enums\ProviderPaymentOutcome;
use App\Models\Payment;
use App\Services\Payments\PaymentGateway;
use App\Support\Money\OmrAmount;
use Illuminate\Console\Command;
use Throwable;

/**
 * CLI-only worker used exclusively by the opt-in payment-concurrency integration test to
 * exercise the *real* settlement path — `ResolvePaymentFromProviderAction`, exactly as the
 * browser return and the reconciliation command use it — from a genuinely separate OS process.
 *
 * The gateway here is a fixed, in-process stand-in that always answers "paid" for this
 * attempt's own reference: no network call is involved, but every identity/amount/currency
 * binding check, the Pending pre-check, and the stale-state-loser handling in
 * `ResolvePaymentFromProviderAction` all run for real. That is deliberate — this worker's job
 * is to prove the *domain* code is race-safe across processes, not to re-prove that Thawani's
 * HTTP API works.
 *
 * Not reachable from the web, requires an exact existing payment reference, and performs
 * exactly what any legitimate provider-verified "paid" answer would already trigger — a thin
 * CLI entry point onto existing domain logic, not a new capability.
 */
class PaymentConcurrencyWorkerCommand extends Command
{
    protected $signature = 'payments:concurrency-worker
                            {reference : The payment\'s public reference}
                            {sessionId : The provider session id to report}
                            {amount : The exact OMR amount to report}
                            {currency=OMR : The currency to report}';

    protected $description = 'Test-only: settles one payment through the real ResolvePaymentFromProviderAction, from a separate OS process. Used by the opt-in payment concurrency integration test.';

    public function handle(): int
    {
        $payment = Payment::withoutGlobalScopes()
            ->where('reference', $this->argument('reference'))
            ->first();

        if ($payment === null) {
            $this->line('ERROR:payment not found');

            return self::FAILURE;
        }

        $gateway = new class($payment->reference, OmrAmount::fromString((string) $this->argument('amount')), (string) $this->argument('currency'), (string) $this->argument('sessionId')) implements PaymentGateway
        {
            public function __construct(
                private readonly string $clientReference,
                private readonly OmrAmount $amount,
                private readonly string $currency,
                private readonly string $sessionId,
            ) {}

            public function createCheckout(CheckoutRequestDTO $request): CheckoutSessionDTO
            {
                throw new \RuntimeException('Not supported by the concurrency-test gateway stand-in.');
            }

            public function retrieveSession(string $sessionId): ProviderSessionStatusDTO
            {
                return new ProviderSessionStatusDTO(
                    sessionId: $this->sessionId,
                    outcome: ProviderPaymentOutcome::PAID,
                    amount: $this->amount,
                    clientReference: $this->clientReference,
                    currency: $this->currency,
                );
            }

            public function retrieveByClientReference(string $clientReference): ?ProviderSessionStatusDTO
            {
                return $this->retrieveSession($this->sessionId);
            }

            public function cancelSession(string $sessionId): bool
            {
                return false;
            }
        };

        try {
            $resolved = (new ResolvePaymentFromProviderAction($gateway))->execute($payment);

            $this->line('RESULT:'.$resolved->status::$name);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->line('ERROR:'.$e::class.':'.$e->getMessage());

            return self::FAILURE;
        }
    }
}
