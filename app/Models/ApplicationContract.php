<?php

namespace App\Models;

use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'application_id',
    'token',
    'token_expires_at',
    'signed_at',
    'signed_by_applicant',
    'file_path',
    'signature_path',
])]
class ApplicationContract extends Model
{
    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
            'signed_at' => 'datetime',
            'signed_by_applicant' => 'boolean',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function isSigned(): bool
    {
        return ! is_null($this->signed_at);
    }

    public function isTokenExpired(): bool
    {
        return $this->token_expires_at && $this->token_expires_at->isPast();
    }
}
