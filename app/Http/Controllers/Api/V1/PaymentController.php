<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Payments\InitiatePaymentAction;
use App\DTOs\Payments\InitiatePaymentDTO;
use App\Enums\PaymentMethod;
use App\Exceptions\PaymentGatewayException;
use App\Exceptions\PaymentInitiationException;
use App\Exceptions\StalePaymentStateException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\InitiateBankTransferPaymentRequest;
use App\Http\Requests\Api\V1\InitiateThawaniPaymentRequest;
use App\Http\Requests\Api\V1\UploadBankReceiptRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Application;
use App\Models\Payment;
use App\Models\Scopes\BranchScope;
use App\States\Payments\AwaitingVerification;
use App\States\Payments\Pending;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * The chatbot/guardian-facing payment surface. Every response is built from `PaymentResource`,
 * so nothing here can leak a provider payload, an internal id, a settings value beyond the
 * bank instructions, or any application/guardian PII beyond what the caller already supplied.
 *
 * Deliberately narrow: cash is staff-only and has no route here (see
 * `PaymentMethod::isAvailableToChatbot()`), and every action is behind its own Sanctum ability
 * plus a named 5/min-per-token limiter (see `routes/api.php` and the `payments` rate limiter).
 *
 * A caller must always supply the application's own reference *and* its guardian phone
 * together. Getting either wrong answers with the same generic 404 — a mismatch never
 * distinguishes "no such application" from "wrong phone", which is what makes the pair
 * un-probeable.
 */
class PaymentController extends Controller
{
    public function initiateThawani(InitiateThawaniPaymentRequest $request, InitiatePaymentAction $action): JsonResponse
    {
        return $this->initiate($request, $action, PaymentMethod::THAWANI);
    }

    public function initiateBankTransfer(InitiateBankTransferPaymentRequest $request, InitiatePaymentAction $action): JsonResponse
    {
        return $this->initiate($request, $action, PaymentMethod::BANK_TRANSFER);
    }

    public function uploadReceipt(UploadBankReceiptRequest $request, string $payment): JsonResponse
    {
        $application = $this->matchingApplication($request);

        if ($application === null) {
            return $this->notFound();
        }

        $record = Payment::withoutGlobalScope(BranchScope::class)
            ->where('reference', $payment)
            ->where('application_id', $application->getKey())
            ->first();

        if ($record === null) {
            return $this->notFound();
        }

        if ($record->method !== PaymentMethod::BANK_TRANSFER || ! $record->status instanceof Pending) {
            return $this->conflict(__('alerts.payment.receipt_upload_not_eligible'));
        }

        $stored = Storage::disk('local')->putFile('receipts', $request->file('receipt'));

        if ($stored === false || ! Storage::disk('local')->exists($stored)) {
            Log::error('A bank-transfer receipt upload was not persisted by the storage driver.', [
                'payment_reference' => $record->reference,
            ]);

            return $this->conflict(__('alerts.payment.receipt_upload_not_eligible'));
        }

        try {
            $updated = $record->status->transitionTo(AwaitingVerification::class, $stored);
        } catch (StalePaymentStateException) {
            // The attempt was resolved by someone else between the lookup above and the
            // transition's own lock re-verification. The just-written file belongs to no
            // persisted payment now, so it is safe — and correct — to remove it: nothing
            // references it, and this compensating delete can never touch a file a
            // successful transition already attached to a payment.
            Storage::disk('local')->delete($stored);

            return $this->conflict(__('alerts.payment.receipt_upload_not_eligible'));
        }

        Log::info('A bank-transfer receipt was uploaded and the attempt moved to awaiting verification.', [
            'payment_reference' => $updated->reference,
        ]);

        return (new PaymentResource($updated))->response();
    }

    private function initiate(Request $request, InitiatePaymentAction $action, PaymentMethod $method): JsonResponse
    {
        $application = $this->matchingApplication($request);

        if ($application === null) {
            return $this->notFound();
        }

        $rawKey = $request->header('Idempotency-Key');

        if (! is_string($rawKey) || trim($rawKey) === '') {
            return $this->conflict(__('alerts.payment.idempotency_key_required'));
        }

        $token = $request->user()?->currentAccessToken();
        $principal = 'token:'.($token?->getKey() ?? 'unknown');

        $dto = new InitiatePaymentDTO(
            application: $application,
            method: $method,
            actor: null,
            idempotencyKey: InitiatePaymentAction::namespacedKey($rawKey, $principal),
            requestHash: hash('sha256', json_encode([
                'application_reference' => $application->ref_no,
                'method' => $method->value,
            ], JSON_THROW_ON_ERROR)),
        );

        try {
            $payment = $action->execute($dto);
        } catch (PaymentInitiationException $e) {
            return $this->conflict($e->getMessage());
        } catch (PaymentGatewayException $e) {
            // The attempt was already created (and, for a final rejection, already failed) by
            // InitiatePaymentAction before this was thrown — there is nothing left to roll
            // back, and the caller is told the same generic conflict rather than the
            // provider's own message.
            Log::warning('Payment initiation via the API could not reach or use the provider.', [
                'application_reference' => $application->ref_no,
                'retryable' => $e->retryable,
            ]);

            return $this->conflict(__('alerts.payment.provider_unavailable'));
        }

        return (new PaymentResource($payment))->response();
    }

    /**
     * Resolves the application by its own reference, and refuses it unless the supplied
     * guardian phone matches — normalised the same way on both sides so a caller who read the
     * number off a different format cannot be refused by formatting alone.
     */
    private function matchingApplication(Request $request): ?Application
    {
        $reference = (string) $request->input('application_reference');
        $suppliedPhone = (string) $request->input('guardian_phone');

        $application = Application::withoutGlobalScope(BranchScope::class)
            ->where('ref_no', $reference)
            ->first();

        if ($application === null || $application->guardian_phone === null) {
            return null;
        }

        try {
            $matches = normalize_phone_number($suppliedPhone) === normalize_phone_number($application->guardian_phone);
        } catch (InvalidArgumentException) {
            return null;
        }

        return $matches ? $application : null;
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['message' => __('alerts.payment.not_found')], 404);
    }

    private function conflict(string $message): JsonResponse
    {
        return response()->json(['message' => $message], 409);
    }
}
