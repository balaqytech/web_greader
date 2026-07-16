<?php

declare(strict_types=1);

namespace App\Services\Payments\Drivers;

use App\DTOs\Payments\CheckoutRequestDTO;
use App\DTOs\Payments\CheckoutSessionDTO;
use App\DTOs\Payments\ProviderSessionStatusDTO;
use App\Enums\ProviderPaymentOutcome;
use App\Exceptions\PaymentGatewayException;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\ThawaniConfig;
use App\Support\Money\OmrAmount;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Jkbroot\Thawani\Api\CheckoutSessions;
use Jkbroot\Thawani\Services\ThawaniService;
use Throwable;

/**
 * The one and only place `jkbroot/thawani` is touched.
 *
 * Everything it returns is translated into this application's DTOs and enums before it
 * leaves, so no package type, provider status string, response array, or baisa integer ever
 * reaches the payment domain — which is what makes the package replaceable.
 *
 * ## Error classification is a money-safety decision, not a convenience
 *
 * The package flattens every non-2xx response into one bare `\Exception`, discarding the
 * HTTP status — so a 400 "invalid amount" and a 500 outage are indistinguishable here. That
 * forces a judgement call, and the two directions are deliberately different:
 *
 *   - **createCheckout** treats a provider error as final. Session creation captures no
 *     money and the guardian has not been redirected anywhere yet, so the worst case is an
 *     orphaned session nobody visits. Failing the attempt is safe, and the guardian simply
 *     retries.
 *   - **retrieveSession / retrieveByClientReference** treat *any* error as retryable. Here
 *     we are asking whether money moved. Concluding "failed" from an error we cannot read
 *     would abandon a payment the guardian may have genuinely made. Not knowing is not the
 *     same as knowing it failed.
 *
 * A connection failure or timeout is always retryable: the request may have taken effect
 * regardless of our never hearing back.
 */
class ThawaniGateway implements PaymentGateway
{
    /**
     * Only these keys are kept from a provider response. An allowlist rather than a
     * denylist: the payload is persisted onto the payment row and shown in support and audit
     * views, and a denylist silently starts leaking the day the provider adds a field.
     *
     * @var list<string>
     */
    private const PAYLOAD_ALLOWLIST = [
        'session_id',
        'client_reference_id',
        'payment_status',
        'mode',
        'currency',
        'total_amount',
        'invoice',
        'expire_at',
        'created_at',
        'success_url',
        'cancel_url',
    ];

    public function createCheckout(CheckoutRequestDTO $request): CheckoutSessionDTO
    {
        $config = ThawaniConfig::fromConfig();

        $response = $this->call(
            fn (CheckoutSessions $sessions): array => $sessions->create([
                'client_reference_id' => $request->clientReference,
                'mode' => 'payment',
                'products' => [[
                    'name' => $request->productName,
                    'quantity' => 1,
                    // The only place OMR becomes baisa. Integer-exact by construction.
                    'unit_amount' => $request->amount->toBaisa(),
                ]],
                'success_url' => $request->successUrl,
                'cancel_url' => $request->cancelUrl,
                'metadata' => $request->metadata,
            ]),
            errorsAreFinal: true,
        );

        $data = $this->data($response);
        $sessionId = $data['session_id'] ?? null;

        if (! is_string($sessionId) || $sessionId === '') {
            throw PaymentGatewayException::unexpectedResponse('the checkout session response contained no session_id.');
        }

        return new CheckoutSessionDTO(
            sessionId: $sessionId,
            checkoutUrl: $this->checkoutUrl($config, $sessionId),
            expiresAt: $this->expiry($data),
            payload: $this->sanitize($data),
        );
    }

    public function retrieveSession(string $sessionId): ProviderSessionStatusDTO
    {
        $response = $this->call(
            fn (CheckoutSessions $sessions): array => $sessions->retrieve($sessionId),
            errorsAreFinal: false,
        );

        return $this->toStatus($this->data($response), $sessionId);
    }

    public function retrieveByClientReference(string $clientReference): ?ProviderSessionStatusDTO
    {
        $response = $this->call(
            fn (CheckoutSessions $sessions): array => $sessions->retrieveByClientReference($clientReference),
            errorsAreFinal: false,
        );

        $data = $this->data($response);

        // Thawani answers this endpoint with a collection. An empty one means it knows of no
        // such session, which is a legitimate answer rather than an error.
        if ($data === []) {
            return null;
        }

        // Tolerate either a bare session object or a list containing one.
        if (array_is_list($data)) {
            $data = $data[0] ?? [];

            if ($data === []) {
                return null;
            }
        }

        $sessionId = $data['session_id'] ?? null;

        if (! is_string($sessionId) || $sessionId === '') {
            throw PaymentGatewayException::unexpectedResponse('a session was returned for the client reference without a session_id.');
        }

        return $this->toStatus($data, $sessionId);
    }

    public function cancelSession(string $sessionId): bool
    {
        try {
            $this->call(
                fn (CheckoutSessions $sessions): array => $sessions->cancel($sessionId),
                errorsAreFinal: true,
            );

            return true;
        } catch (PaymentGatewayException $e) {
            // Cancellation is best effort. A provider that refuses — almost always because
            // the session is already settled — is answering, not failing, and must not take
            // down the caller. A connectivity problem is still surfaced.
            if ($e->retryable) {
                throw $e;
            }

            return false;
        }
    }

    /**
     * Runs a package call, normalising every failure into PaymentGatewayException.
     *
     * `$errorsAreFinal` encodes the asymmetry documented on this class: creating a session
     * may be failed on a provider error, but asking whether money moved may not.
     *
     * @param  callable(CheckoutSessions): array<mixed>  $operation
     * @return array<mixed>
     *
     * @throws PaymentGatewayException
     */
    private function call(callable $operation, bool $errorsAreFinal): array
    {
        // Built per call rather than resolved from the container: the package registers
        // ThawaniService as a singleton that reads config in its constructor, so a container
        // instance can outlive a config change and silently keep using stale credentials.
        // ThawaniConfig::fromConfig() has already validated the config this reads.
        ThawaniConfig::fromConfig();

        try {
            return $operation(new CheckoutSessions(new ThawaniService));
        } catch (ConnectionException $e) {
            // Never reached the provider, or never heard back. The request may still have
            // taken effect on their side, so this is always retryable.
            throw PaymentGatewayException::unreachable($e);
        } catch (PaymentGatewayException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw $errorsAreFinal
                ? PaymentGatewayException::rejected($e->getMessage(), (int) $e->getCode(), $e)
                : PaymentGatewayException::unexpectedResponse($e->getMessage(), $e);
        }
    }

    /**
     * @param  array<mixed>  $response
     * @return array<mixed>
     */
    private function data(array $response): array
    {
        $data = $response['data'] ?? null;

        if (! is_array($data)) {
            throw PaymentGatewayException::unexpectedResponse('the response contained no data object.');
        }

        return $data;
    }

    /**
     * @param  array<mixed>  $data
     */
    private function toStatus(array $data, string $sessionId): ProviderSessionStatusDTO
    {
        return new ProviderSessionStatusDTO(
            sessionId: $sessionId,
            outcome: $this->outcome($data),
            amount: $this->amount($data),
            clientReference: is_string($data['client_reference_id'] ?? null) ? $data['client_reference_id'] : null,
            payload: $this->sanitize($data),
        );
    }

    /**
     * Maps Thawani's `payment_status` onto this domain's outcomes.
     *
     * Only an explicit "paid" settles a fee. Anything unrecognised becomes UNKNOWN, which
     * leaves the attempt pending and retryable — guessing an unmapped status into PAID would
     * invent money, and guessing it into FAILED would abandon it. An expired-but-unpaid
     * session is reported as EXPIRED rather than merely unpaid, since its window has closed
     * and it can never become paid.
     *
     * @param  array<mixed>  $data
     */
    private function outcome(array $data): ProviderPaymentOutcome
    {
        $status = $data['payment_status'] ?? null;

        if (! is_string($status)) {
            return ProviderPaymentOutcome::UNKNOWN;
        }

        return match (strtolower($status)) {
            'paid' => ProviderPaymentOutcome::PAID,
            'cancelled', 'canceled' => ProviderPaymentOutcome::CANCELLED,
            'failed', 'declined' => ProviderPaymentOutcome::FAILED,
            'expired' => ProviderPaymentOutcome::EXPIRED,
            'unpaid', 'pending' => $this->hasLapsed($data)
                ? ProviderPaymentOutcome::EXPIRED
                : ProviderPaymentOutcome::UNPAID,
            default => ProviderPaymentOutcome::UNKNOWN,
        };
    }

    /**
     * @param  array<mixed>  $data
     */
    private function hasLapsed(array $data): bool
    {
        $expiry = $this->expiry($data);

        return $expiry !== null && $expiry->isPast();
    }

    /**
     * @param  array<mixed>  $data
     */
    private function expiry(array $data): ?CarbonImmutable
    {
        $expiry = $data['expire_at'] ?? null;

        if (! is_string($expiry) && ! is_int($expiry)) {
            return null;
        }

        try {
            return is_int($expiry)
                ? CarbonImmutable::createFromTimestamp($expiry)
                : CarbonImmutable::parse($expiry);
        } catch (Throwable) {
            // An unparseable expiry must not take down a status read; the attempt simply has
            // no known expiry and is resolved by asking the provider again.
            return null;
        }
    }

    /**
     * The provider reports totals in baisa. Converted back through OmrAmount so the domain
     * only ever sees exact decimal OMR, and so a nonsensical total is dropped rather than
     * silently mangled.
     *
     * @param  array<mixed>  $data
     */
    private function amount(array $data): ?OmrAmount
    {
        $total = $data['total_amount'] ?? null;

        if (! is_int($total) && ! (is_string($total) && ctype_digit($total))) {
            return null;
        }

        try {
            return OmrAmount::fromBaisa((int) $total);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitize(array $data): array
    {
        return array_filter(
            array_intersect_key($data, array_flip(self::PAYLOAD_ALLOWLIST)),
            fn (mixed $value): bool => is_scalar($value) || $value === null,
        );
    }

    /**
     * Thawani's hosted checkout URL. The publishable key is public by design — it is what
     * identifies the merchant to the browser — and is never the secret key.
     */
    private function checkoutUrl(ThawaniConfig $config, string $sessionId): string
    {
        return sprintf(
            '%s/%s?key=%s',
            rtrim($config->checkoutBaseUrl, '/'),
            $sessionId,
            $config->publishableKey,
        );
    }
}
