<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class BranchHasProgram implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $studentIndex = explode('.', $attribute)[1];
        $programId = request()->input("students.{$studentIndex}.program_id");

        if ($programId) {
            $branchHasProgram = DB::table('branch_program')
                ->where('branch_id', $value)
                ->where('program_id', $programId)
                ->exists();

            if (!$branchHasProgram) {
                $fail(__('validation.branch_has_program'));
            }
        }
    }
}
