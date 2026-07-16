<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Support\Settings\PaymentSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The API's entire public view of a payment. Deliberately an allowlist, not a passthrough of
 * the model: a payment row carries provider payloads, internal keys, and staff-only fields
 * that must never reach the chatbot or a guardian's browser.
 *
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'method' => $this->method->value,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status::$name,
            'status_label' => $this->status->getLabel(),
            'checkout_url' => $this->method === PaymentMethod::THAWANI
                ? $this->provider_checkout_url
                : null,
            'bank_instructions' => $this->method === PaymentMethod::BANK_TRANSFER
                ? app(PaymentSettings::class)->bankTransferInstructions()
                : null,
        ];
    }
}
