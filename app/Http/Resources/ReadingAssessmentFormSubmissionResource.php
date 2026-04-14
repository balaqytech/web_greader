<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReadingAssessmentFormSubmissionResource extends JsonResource
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
            'student_name' => $this->student_name,
            'age' => $this->age,
            'grade_level' => $this->grade_level,
            'guardian_name' => $this->guardian_name,
            'whatsapp' => $this->whatsapp,
            'branch' => $this->branch->toArray(),
            'additional_info' => $this->additional_info,
        ];
    }
}
