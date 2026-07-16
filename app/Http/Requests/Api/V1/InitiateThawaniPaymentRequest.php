<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Ability and rate-limit are enforced by route middleware; validation here is purely shape —
 * the actual application + guardian match happens in the controller so a mismatch answers
 * generically (404) rather than leaking which part of the pair was wrong.
 */
class InitiateThawaniPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'application_reference' => ['required', 'string', 'max:64'],
            'guardian_phone' => ['required', 'string', 'max:32'],
        ];
    }
}
