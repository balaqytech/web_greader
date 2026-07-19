<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\PaymentPurpose;
use App\Models\Application;
use App\Models\Payment;
use App\Models\Scopes\BranchScope;
use App\Support\Applications\ApplicationNextStep;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The verified status projection for an application. A strict allowlist: it exposes the public
 * reference, the current state (code + label), the single next step (code + label), and the
 * latest registration-fee payment as a minimal record — nothing else. Student/guardian
 * details, documents, contract tokens/URLs, provider payloads, rejection reasons, internal
 * ids, and payment internals are all deliberately absent.
 *
 * @mixin Application
 */
class ApplicationStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $nextStep = ApplicationNextStep::code($this->resource);

        return [
            'application_reference' => $this->ref_no,
            'status' => $this->status::$name,
            'status_label' => $this->status->getLabel(),
            'next_step' => $nextStep,
            'next_step_label' => ApplicationNextStep::label($nextStep),
            'registration_payment' => $this->latestRegistrationPayment(),
        ];
    }

    /**
     * The most recent registration-fee attempt, projected to the same minimal shape as the
     * payment API. Bypasses only BranchScope so the branchless service account can read it.
     *
     * @return array<string, string>|null
     */
    private function latestRegistrationPayment(): ?array
    {
        $payment = Payment::withoutGlobalScope(BranchScope::class)
            ->where('application_id', $this->getKey())
            ->where('purpose', PaymentPurpose::REGISTRATION_FEE)
            ->latest('id')
            ->first();

        if ($payment === null) {
            return null;
        }

        return [
            'reference' => $payment->reference,
            'method' => $payment->method->value,
            'status' => $payment->status::$name,
            'status_label' => $payment->status->getLabel(),
        ];
    }
}
