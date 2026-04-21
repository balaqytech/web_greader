<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BotContactResource extends JsonResource
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
            'channel' => $this->channel,
            'sender_name' => $this->sender_name,
            'whatsapp' => $this->whatsapp,
            'status' => $this->status,
            'notes' => $this->notes,
            'additional_data' => $this->additional_data,
            'metadata' => $this->metadata,
        ];
    }
}
