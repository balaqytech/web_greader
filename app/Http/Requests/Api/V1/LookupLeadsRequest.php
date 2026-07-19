<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `GET /leads` is an exact-whatsapp lookup, not a browsable index — the number is required so
 * the endpoint can never be coerced into listing every lead. Ability and the service-account
 * gate are enforced by route middleware; this request is purely shape.
 */
class LookupLeadsRequest extends FormRequest
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
            'whatsapp' => ['required', 'string', 'min:8', 'max:16'],
        ];
    }
}
