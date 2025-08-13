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
            'description' => $this->description,
            'type' => $this->type->getLabel(),
            'base_price' => $this->base_price->value(),
            'payment_type' => $this->payment_type->getLabel(),
            'branches' => BranchResource::collection($this->whenLoaded('branches')),
            'additional_info' => $this->additional_info,
        ];
    }
}
