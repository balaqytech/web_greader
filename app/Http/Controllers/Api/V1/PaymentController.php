<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Payments\InitiatePaymentAction;
use App\DTOs\Payments\InitiatePaymentDTO;
use App\Enums\PaymentMethod;
use App\Exceptions\InvalidSettlementEvidenceException;
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
use App\Support\Payments\PaymentReceiptStorage;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

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

    public function uploadReceipt(
        UploadBankReceiptRequest $request,
        string $payment,
        PaymentReceiptStorage $receipts,
    ): JsonResponse {
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

        $rawKey = $request->header('Idempotency-Key');

        if (! $this->validRawIdempotencyKey($rawKey)) {
            return $this->conflict(__('alerts.payment.idempotency_key_required'));
        }

        $uploadedFile = $request->file('receipt');
        $realPath = $uploadedFile?->getRealPath();
        $fileHash = is_string($realPath) ? hash_file('sha256', $realPath) : false;

        if (! is_string($fileHash)) {
            return $this->conflict(__('alerts.payment.receipt_upload_not_eligible'));
        }

        $idempotencyKey = InitiatePaymentAction::namespacedKey($rawKey, $this->tokenPrincipal($request));
        $requestHash = hash('sha256', json_encode([
            'application_reference' => $application->ref_no,
            'payment_reference' => $record->reference,
            'file_sha256' => $fileHash,
        ], JSON_THROW_ON_ERROR));

        try {
            if (($replay = $this->receiptReplay($record, $idempotencyKey, $requestHash)) !== null) {
                return (new PaymentResource($replay))->response();
            }
        } catch (PaymentInitiationException $e) {
            return $this->conflict($e->getMessage());
        }

        if ($record->method !== PaymentMethod::BANK_TRANSFER || ! $record->status instanceof Pending) {
            return $this->conflict(__('alerts.payment.receipt_upload_not_eligible'));
        }

        $stored = Storage::disk('local')->putFile('receipts', $uploadedFile);

        if ($stored === false || ! $receipts->exists($stored)) {
            Log::error('A bank-transfer receipt upload was not persisted by the storage driver.', [
                'payment_reference' => $record->reference,
            ]);

            return $this->conflict(__('alerts.payment.receipt_upload_not_eligible'));
        }

        try {
            $updated = $record->status->transitionTo(
                AwaitingVerification::class,
                $stored,
                $idempotencyKey,
                $requestHash,
            );
        } catch (StalePaymentStateException|InvalidSettlementEvidenceException|UniqueConstraintViolationException|InvalidArgumentException) {
            $receipts->deleteIfUnreferenced($stored);

            try {
                if (($replay = $this->receiptReplay($record, $idempotencyKey, $requestHash)) !== null) {
                    return (new PaymentResource($replay))->response();
                }
            } catch (PaymentInitiationException $e) {
                return $this->conflict($e->getMessage());
            }

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

        if (! $this->validRawIdempotencyKey($rawKey)) {
            return $this->conflict(__('alerts.payment.idempotency_key_required'));
        }

        $dto = new InitiatePaymentDTO(
            application: $application,
            method: $method,
            actor: null,
            idempotencyKey: InitiatePaymentAction::namespacedKey($rawKey, $this->tokenPrincipal($request)),
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
            Log::warning('Payment initiation via the API could not reach or use the provider.', [
                'application_reference' => $application->ref_no,
                'retryable' => $e->retryable,
            ]);

            return $this->conflict(__('alerts.payment.provider_unavailable'));
        }

        return (new PaymentResource($payment))->response();
    }

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

    private function tokenPrincipal(Request $request): string
    {
        return 'token:'.($request->user()?->currentAccessToken()?->getKey() ?? 'unknown');
    }

    private function validRawIdempotencyKey(mixed $key): bool
    {
        return is_string($key) && trim($key) !== '' && mb_strlen($key) <= 128;
    }

    /**
     * @throws PaymentInitiationException
     */
    private function receiptReplay(Payment $expected, string $key, string $requestHash): ?Payment
    {
        $existing = Payment::withoutGlobalScope(BranchScope::class)
            ->where('receipt_idempotency_key', $key)
            ->first();

        if ($existing === null) {
            return null;
        }

        if (! $existing->is($expected)
            || ! is_string($existing->receipt_request_hash)
            || ! hash_equals($existing->receipt_request_hash, $requestHash)) {
            throw PaymentInitiationException::idempotencyKeyReused();
        }

        return $existing;
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
