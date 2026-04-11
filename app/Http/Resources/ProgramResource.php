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
            'base_price' => $this->base_price,
            'accept_installments' => $this->accept_installments,
            'is_open' => $this->is_open,
            'branches' => $this->branches->pluck('name', 'id'),
        ];
    }
}
