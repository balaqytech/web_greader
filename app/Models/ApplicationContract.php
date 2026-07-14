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
        return (bool) $this->signed_at
            && $this->file_path !== null
            && $this->signature_path !== null
            && $this->token !== null;
    }

    /**
     * A contract is "signed off" once it has a signature timestamp and a stored signed
     * artifact. This supports both signing paths: electronic signing (which also stores a
     * signature_path) and staff upload of a signed copy (which stores only file_path).
     */
    public function isSignedOff(): bool
    {
        return $this->signed_at !== null && $this->file_path !== null;
    }

    public function isTokenExpired(): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->isPast();
    }
}
