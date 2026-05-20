<?php

namespace App\Models;

use App\Enums\BotContactStatusEnum;
use App\Support\Model;
use App\Traits\HasWhatsapp;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'channel',
    'sender_name',
    'whatsapp',
    'status',
    'conversation_summary',
    'rejection_reason',
    'notes',
    'additional_data',
    'metadata',
])]
class BotContact extends Model
{
    // use HasWhatsapp;

    protected $casts = [
        'status' => BotContactStatusEnum::class,
        'additional_data' => 'json',
        'metadata' => 'json',
    ];
}
