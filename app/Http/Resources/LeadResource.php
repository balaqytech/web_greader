<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ref_no' => $this->ref_no,
            'guardian_name' => $this->guardian_name,
            'student_name' => $this->student_name,
            'whatsapp' => $this->whatsapp,
            'branch_id' => $this->branch_id,
            'season_id' => $this->season_id,
            'program_id' => $this->program_id,
            'program_type' => $this->program_type->getLabel(),
            'status' => $this->status,
            'status_label' => $this->status->getLabel(),
            'source' => $this->source,
            'data' => $this->data,
        ];
    }
}
