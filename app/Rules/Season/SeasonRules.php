<?php

namespace App\Rules\Season;

use App\Enums\ProgramType;
use Illuminate\Validation\Rule;

/**
 * Shared validation rules for Season create and update operations.
 */
class SeasonRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ProgramType::class)],
            'start_date' => ['nullable', 'date', 'before_or_equal:end_date'],
            'end_date' => ['required', Rule::date()->todayOrBefore()],
            'is_registration_open' => ['sometimes', 'boolean'],
        ];
    }
}
