<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Payments\ResolvePaymentFromProviderAction;
use App\Exceptions\PaymentGatewayException;
use App\Models\Payment;
use App\Models\Scopes\BranchScope;
use App\States\Payments\Expired;
use App\States\Payments\Failed;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Where Thawani sends the guardian's browser after checkout.
 *
 * **The redirect proves nothing.** It is client-controlled — a guardian can type
 * `?outcome=success` into the URL bar — so `outcome` is never read as a result. It is
 * treated purely as a hint that it is worth asking the provider, and the answer only ever
 * comes from a server-to-server session retrieval.
 *
 * Public and unauthenticated by necessity: the guardian arrives from Thawani's hosted page
 * with no session. The payment is therefore addressed by its unguessable ULID rather than an
 * enumerable id, and the page discloses only that payment's own status — never the
 * application's data, the guardian's details, or any provider internals.
 */
class PaymentReturnController extends Controller
{
    public function __invoke(
        Request $request,
        string $payment,
        ResolvePaymentFromProviderAction $resolve,
    ): View {
        // BranchScope is bypassed deliberately: there is no authenticated user here, and the
        // ULID is the authorization. A scoped lookup would 404 every real return.
        $record = Payment::withoutGlobalScope(BranchScope::class)
            ->where('reference', $payment)
            ->firstOrFail();

        try {
            $record = $resolve->execute($record);
            $message = $this->messageFor($record);
        } catch (PaymentGatewayException) {
            // The provider could not be asked. Nothing is concluded — the attempt stays
            // pending and reconciliation will settle it. The guardian is told the truth
            // rather than shown a guess.
            $message = __('alerts.payment.return_unavailable');
        }

        return view('payments.return', [
            'reference' => $record->reference,
            'status' => $record->status,
            'message' => $message,
        ]);
    }

    private function messageFor(Payment $payment): string
    {
        if ($payment->isPaid()) {
            return __('alerts.payment.return_paid');
        }

        if ($payment->status instanceof Failed || $payment->status instanceof Expired) {
            return __('alerts.payment.return_failed');
        }

        return __('alerts.payment.return_pending');
    }
}
