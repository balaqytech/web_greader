<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgramResource extends JsonResource
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
            'name' => $this->name,
            'type' => $this->type->getLabel(),
            'description' => $this->description,
            'min_birth_date' => $this->min_birth_date,
            'max_birth_date' => $this->max_birth_date,
            'is_open' => $this->is_open,
            'branches' => $this->branches->map(function ($branch) {
                return [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'price' => $branch->pivot->price,
                ];
            }),
        ];
    }
}
