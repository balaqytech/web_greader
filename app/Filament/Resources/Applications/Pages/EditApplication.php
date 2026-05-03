<?php

namespace App\Filament\Resources\Applications\Pages;

use App\Filament\Resources\Applications\ApplicationResource;
use App\Models\Application;
use App\Models\ApplicationContact;
use App\Models\ApplicationStudent;
use App\Models\Program;
use App\Models\Season;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditApplication extends EditRecord
{
    protected static string $resource = ApplicationResource::class;

    /**
     * Load the applicationStudent and contacts relationship data into the form state
     * so that dot-notation fields like `applicationStudent.name` and the contacts
     * repeater are properly filled.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Application $record */
        $record = $this->getRecord();
        $record->loadMissing(['applicationStudent', 'contacts']);

        // Flatten applicationStudent relationship into form data
        if ($record->applicationStudent) {
            $student = $record->applicationStudent;
            $data['applicationStudent'] = [
                'name' => $student->name,
                'gender' => $student->gender?->value,
                'birth_date' => $student->birth_date?->format('Y-m-d'),
                'civil_number' => $student->civil_number,
                'state' => $student->state,
                'governorate' => $student->governorate,
                'village' => $student->village,
                'house_number' => $student->house_number,
                'parents_social_status' => $student->parents_social_status,
                'relationship_with_guardian' => $student->relationship_with_guardian?->value,
            ];
        }

        // Flatten contacts relationship into form data
        $data['contacts'] = $record->contacts->map(fn (ApplicationContact $contact) => [
            'relationship' => $contact->relationship?->value,
            'name' => $contact->name,
            'phone' => $contact->phone,
            'email' => $contact->email,
            'id_number' => $contact->id_number,
            'occupation' => $contact->occupation,
            'work_address' => $contact->work_address,
            'work_phone' => $contact->work_phone,
            'is_guardian' => (bool) $contact->is_guardian,
        ])->toArray();

        return $data;
    }

    /**
     * Handle saving the Application + its child records (student & contacts).
     * Auto-resolve season_id from the selected program.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Application $record */

        // Auto-resolve season from program
        $program = Program::findOrFail($data['program_id']);
        $data['season_id'] = Season::current($program->type)->id;

        // Update Application core fields
        $record->update([
            'branch_id' => $data['branch_id'],
            'program_id' => $data['program_id'],
            'season_id' => $data['season_id'],
        ]);

        // Update or create ApplicationStudent
        $studentData = $data['applicationStudent'] ?? [];
        if (filled($studentData['name'] ?? null)) {
            if ($record->applicationStudent) {
                $record->applicationStudent->update($studentData);
            } else {
                ApplicationStudent::create([
                    'application_id' => $record->id,
                    ...$studentData,
                ]);
            }
        }

        // Sync contacts: delete old, create new from form data
        $record->contacts()->delete();
        $contactsData = $data['contacts'] ?? [];
        foreach ($contactsData as $contactData) {
            if (filled($contactData['name'] ?? null)) {
                ApplicationContact::create([
                    'application_id' => $record->id,
                    ...$contactData,
                ]);
            }
        }

        return $record->fresh();
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
