<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class InitiateBankTransferPaymentRequest extends FormRequest
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
