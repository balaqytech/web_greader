<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Actions\Leads\CreateLeadAction;
use App\Filament\Resources\Leads\LeadResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateLead extends CreateRecord
{
    protected static string $resource = LeadResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateLeadAction::class)->execute(
            whatsapp: $data['whatsapp'],
            guardian_name: $data['guardian_name'],
            student_name: $data['student_name'],
            program_id: $data['program_id'],
            branch_id: $data['branch_id'],
            source: 'dashboard',
            data: $data['data'],
        );
    }
}
