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
            'branch' => [
                'id' => $this->branch_id,
                'name' => $this->branch->name,
                'governorate' => $this->branch->governorate,
            ],
            'season' => [
                'id' => $this->season_id,
                'name' => $this->season->name,
            ],
            'program' => [
                'id' => $this->program_id,
                'name' => $this->program->name,
            ],
            'program_type' => $this->program_type->getLabel(),
            'status' => $this->status,
            'status_label' => $this->status->getLabel(),
            'source' => $this->source,
            'data' => $this->data,
            'affiliate' => $this->whenLoaded('affiliate', function () {
                return [
                    'id' => $this->affiliate->id,
                    'name' => $this->affiliate->name,
                    'code' => $this->affiliate->code,
                    'whatsapp' => $this->affiliate->whatsapp,
                ];
            }),
            'created_at' => $this->created_at->format('d/m/Y H:i:s'),
        ];
    }
}
