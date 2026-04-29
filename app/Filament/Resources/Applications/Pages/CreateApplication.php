<?php

namespace App\Filament\Resources\Applications\Pages;

use App\Actions\Applications\CreateApplicationAction;
use App\DTOs\Application\CreateApplicationDTO;
use App\Enums\Source;
use App\Filament\Resources\Applications\ApplicationResource;
use App\Filament\Resources\Applications\Schemas\CreateApplicationForm;
use App\Models\Application;
use App\States\Applications\DataComplete;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

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
        $dto = new CreateApplicationDTO(
            programId: $data['program_id'],
            leadId: null,
            branchId: $data['branch_id'],
            seasonId: $data['season_id'],
            source: Source::DASHBOARD,
            affiliateId: null,
            studentName: $data['student_name'],
            studentGender: $data['student_gender'] ?? null,
            studentBirthDate: $data['student_birth_date'] ?? null,
            studentCivilNumber: $data['student_civil_number'] ?? null,
            studentState: $data['student_state'] ?? null,
            studentGovernorate: $data['student_governorate'] ?? null,
            studentVillage: $data['student_village'] ?? null,
            studentHouseNumber: $data['student_house_number'] ?? null,
            studentParentsSocialStatus: $data['student_parents_social_status'] ?? null,
            fatherName: $data['father_name'] ?? null,
            fatherPhone: $data['father_phone'] ?? null,
            fatherEmail: $data['father_email'] ?? null,
            fatherIdNumber: $data['father_id_number'] ?? null,
            fatherOccupation: $data['father_occupation'] ?? null,
            fatherWorkAddress: $data['father_work_address'] ?? null,
            fatherWorkPhone: $data['father_work_phone'] ?? null,
            fatherIsGuardian: (bool) ($data['father_is_guardian'] ?? false),
            motherName: $data['mother_name'] ?? null,
            motherPhone: $data['mother_phone'] ?? null,
            motherEmail: $data['mother_email'] ?? null,
            motherIdNumber: $data['mother_id_number'] ?? null,
            motherOccupation: $data['mother_occupation'] ?? null,
            motherWorkAddress: $data['mother_work_address'] ?? null,
            motherWorkPhone: $data['mother_work_phone'] ?? null,
            motherIsGuardian: (bool) ($data['mother_is_guardian'] ?? false),
            relativeName: $data['relative_name'] ?? null,
            relativePhone: $data['relative_phone'] ?? null,
            relativeEmail: $data['relative_email'] ?? null,
            relativeIdNumber: $data['relative_id_number'] ?? null,
            relativeOccupation: $data['relative_occupation'] ?? null,
            relativeWorkAddress: $data['relative_work_address'] ?? null,
            relativeWorkPhone: $data['relative_work_phone'] ?? null,
        );

        /** @var Application $application */
        $application = app(CreateApplicationAction::class)->execute($dto);

        // Transition directly to DataComplete since staff created with full data
        try {
            $application->status->transitionTo(
                DataComplete::class,
                transitionedBy: Auth::id(),
                notes: __('admin.application.created_by_staff_note'),
            );
        } catch (\Exception) {
            // If transition fails, leave in PendingRegistration — staff can complete manually
        }

        return $application->fresh();
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
