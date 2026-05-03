<?php

namespace App\Actions\Applications;

use App\Models\Guardian;
use App\Models\Student;

class PrefillFormFromExistingAction
{
    /**
     * Build form data from an existing student + their contacts.
     *
     * @return array{applicationStudent: array<string, mixed>, contacts: array<int, array<string, mixed>>}
     */
    public static function fromStudent(Student $student): array
    {
        $student->loadMissing('contacts');

        return [
            'applicationStudent' => [
                'name' => $student->name,
                'gender' => $student->gender?->value,
                'birth_date' => $student->birth_date?->format('Y-m-d'),
                'civil_number' => $student->civil_number,
                'state' => $student->state,
                'governorate' => $student->governorate,
                'village' => $student->village,
                'house_number' => $student->house_number,
                'parents_social_status' => $student->parents_social_status,
            ],
            'contacts' => self::mapContacts($student),
        ];
    }

    /**
     * Build contacts-only data from a guardian's most recent student.
     * Used when the guardian is known but enrolling a new (different) student.
     *
     * @return array{contacts: array<int, array<string, mixed>>}
     */
    public static function fromGuardianOnly(Guardian $guardian): array
    {
        $guardian->loadMissing('students.contacts');

        $latestStudent = $guardian->students
            ->sortByDesc('created_at')
            ->first();

        if ($latestStudent && $latestStudent->contacts->isNotEmpty()) {
            return [
                'contacts' => self::mapContacts($latestStudent),
            ];
        }

        // Fallback: build a single guardian contact from the Guardian model itself
        return [
            'contacts' => [
                [
                    'type' => $guardian->relationship?->value ?? 'father',
                    'name' => $guardian->name,
                    'phone' => $guardian->phone,
                    'email' => $guardian->email,
                    'id_number' => $guardian->id_number,
                    'occupation' => $guardian->occupation,
                    'work_address' => $guardian->work_address,
                    'work_phone' => $guardian->work_phone,
                    'is_guardian' => true,
                    'relationship' => null,
                ],
            ],
        ];
    }

    /**
     * Map a student's contacts to the form array format.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function mapContacts(Student $student): array
    {
        return $student->contacts->map(fn ($contact) => [
            'type' => $contact->type?->value,
            'relationship' => $contact->relationship,
            'name' => $contact->name,
            'phone' => $contact->phone,
            'email' => $contact->email,
            'id_number' => $contact->id_number,
            'occupation' => $contact->occupation,
            'work_address' => $contact->work_address,
            'work_phone' => $contact->work_phone,
            'is_guardian' => (bool) $contact->is_guardian,
        ])->values()->toArray();
    }
}
