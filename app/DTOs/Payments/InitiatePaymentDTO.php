<?php

declare(strict_types=1);

namespace App\DTOs\Payments;

use App\Enums\PaymentMethod;
use App\Exceptions\PaymentInitiationException;
use App\Models\Application;
use App\Models\User;

/**
 * A request to start paying an application's registration fee.
 *
 * `idempotencyKey` must already be namespaced by the acting principal — see
 * `InitiatePaymentAction::namespacedKey()`. A raw client-supplied key is never stored as-is:
 * two callers picking the same obvious value ("1") must not collide, and one caller must
 * never be able to probe or retrieve another's payment by guessing theirs.
 *
 * `idempotencyKey` and `requestHash` must both be present or both be absent — a key with no
 * hash could never detect a conflicting reuse, and a hash with no key has nothing to key on.
 */
final readonly class InitiatePaymentDTO
{
    public function __construct(
        public Application $application,
        public PaymentMethod $method,
        public ?User $actor = null,
        public ?string $idempotencyKey = null,
        public ?string $requestHash = null,
    ) {
        if (($this->idempotencyKey === null) !== ($this->requestHash === null)) {
            throw PaymentInitiationException::idempotencyRequiresHash();
        }
    }
}
