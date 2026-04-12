<?php

namespace App\Actions\Leads;

use App\Models\Lead;
use App\Models\Program;
use App\Models\Season;

class CreateLeadAction
{
    /**
     * Create a new class instance.
     */
    public function execute(
        string $whatsapp,
        string $guardian_name,
        string $student_name,
        int $program_id,
        int $branch_id,
        string $source,
        array $data = [],
    ): Lead {
        $ref_no = $this->generateRefNo();
        $program_type = Program::find($program_id)?->type;
        $season_id = Season::current($program_type)->id;

        $whatsapp = $this->formatWhatsapp($whatsapp);

        return Lead::create([
            'ref_no' => $ref_no,
            'whatsapp' => $whatsapp,
            'guardian_name' => $guardian_name,
            'student_name' => $student_name,
            'program_id' => $program_id,
            'branch_id' => $branch_id,
            'source' => $source,
            'program_type' => $program_type,
            'season_id' => $season_id,
            'data' => $data,
        ]);
    }

    private function generateRefNo(): string
    {
        return now()->format('Ymd').str_pad(Lead::count() + 1, 6, '0', STR_PAD_LEFT);
    }

    private function formatWhatsapp(string $whatsapp): string
    {
        $whatsapp = convert_eastern_arabic_to_arabic($whatsapp);
        $whatsapp = normalize_phone_number($whatsapp);

        return $whatsapp;
    }
}
