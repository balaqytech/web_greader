<?php

namespace App\Actions\Applications;

use App\DTOs\Application\CreateApplicationDTO;
use App\Models\Application;
use Illuminate\Support\Facades\DB;

class CreateApplicationAction
{
    public function execute(CreateApplicationDTO $dto): Application
    {
        return DB::transaction(function () use ($dto) {
            /** @var Application $application */
            $application = Application::create($dto->toApplicationArray());

            // Create ApplicationStudent if student data is provided
            $studentData = $dto->toStudentArray();
            if (filled($studentData['name'] ?? null)) {
                $application->applicationStudent()->create($studentData);
            }

            // Create ApplicationContacts
            foreach ($dto->toContactsArray() as $contactData) {
                if (filled($contactData['name'] ?? null)) {
                    $application->contacts()->create($contactData);
                }
            }

            return $application;
        });
    }
}
