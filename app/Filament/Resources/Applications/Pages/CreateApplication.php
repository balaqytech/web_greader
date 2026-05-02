<?php

namespace App\Filament\Resources\Applications\Pages;

use App\Filament\Resources\Applications\ApplicationResource;
use App\Filament\Resources\Applications\Schemas\CreateApplicationForm;
use App\Models\Application;
use App\Models\ApplicationContact;
use App\Models\ApplicationStudent;
use App\States\Applications\Draft;
use App\States\Applications\Submitted;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateApplication extends CreateRecord
{
    protected static string $resource = ApplicationResource::class;

    public function getTitle(): string
    {
        return __('admin.application.actions.create');
    }

    public function form(Schema $schema): Schema
    {
        return CreateApplicationForm::configure($schema);
    }

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            /** @var Application $application */
            $application = Application::create([
                'program_id' => $data['program_id'],
                'branch_id' => $data['branch_id'],
                'season_id' => $data['season_id'],
                'status' => Draft::$name,
            ]);

            // Create ApplicationStudent from wizard data
            $studentData = $data['applicationStudent'] ?? [];
            if (filled($studentData['name'] ?? null)) {
                ApplicationStudent::create([
                    'application_id' => $application->id,
                    ...$studentData,
                ]);
            }

            // Create ApplicationContacts from wizard data
            $contactsData = $data['contacts'] ?? [];
            foreach ($contactsData as $contactData) {
                if (filled($contactData['name'] ?? null)) {
                    ApplicationContact::create([
                        'application_id' => $application->id,
                        ...$contactData,
                    ]);
                }
            }

            // Try to transition to Submitted if data looks complete
            try {
                $application->status->transitionTo(Submitted::class);
            } catch (\Exception) {
                // If transition fails, leave in Draft — staff can complete manually
            }

            return $application->fresh();
        });
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->title(__('admin.application.actions.create_success'))
            ->success();
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
