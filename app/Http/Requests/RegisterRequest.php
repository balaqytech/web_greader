<?php

namespace App\Http\Requests;

use App\Enums\Gender;
use App\Rules\BranchHasProgram;
use Illuminate\Validation\Rule;
use App\Enums\RelationshipWithParent;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'parent' => 'required|array',
            'parent.name' => 'required|string',
            'parent.email' => 'required|email',
            'parent.phone' => 'required|string',
            'parent.address' => 'required|string',
            'parent.city' => 'required|string',
            'parent.branch_id' => 'required|exists:branches,id',
            'parent.additional_info' => 'nullable|array',
            'students' => 'required|array',
            'students.*.name' => 'required|string',
            'students.*.date_of_birth' => 'required|date',
            'students.*.gender' => ['required', Rule::enum(Gender::class)],
            'students.*.relationship_with_parent' => ['required', Rule::enum(RelationshipWithParent::class)],
            'students.*.program_id' => 'required|exists:programs,id',
                        'students.*.branch_id' => [
                'required',
                'exists:branches,id',
                new BranchHasProgram,
            ],
            'additional_info' => 'nullable|array',
        ];
    }

    /** @mixin \Illuminate\Foundation\Http\FormRequest $this */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'parent.phone' => convert_eastern_arabic_to_arabic($this->input('parent.phone', '')),
            'students' => collect($this->input('students', []))->map(function ($student) {
                $student['date_of_birth'] = convert_eastern_arabic_to_arabic($student['date_of_birth'] ?? '');
                return $student;
            })->toArray(),
        ]);
    }
}
