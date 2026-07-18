<?php

namespace App\Models;

use App\Exceptions\ContractImmutabilityException;
use App\States\Contracts\ContractState;
use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\ModelStates\HasStates;

/**
 * A single immutable contract version (§3.5, §5.5). `rendered_body` and `template_hash` are
 * frozen at generation and never rewritten, so display and signing replay exactly what the
 * signer saw and a later template edit cannot retroactively change a version.
 *
 * `file_path` stores a public-disk-relative path for every version created under versioning.
 * Legacy rows may hold a full absolute URL (the pre-versioning online-signing path wrote
 * `Storage::disk('public')->url(...)`); `signedFileUrl()` reads both shapes so callers never
 * have to special-case them.
 */
#[Fillable([
    'application_id',
    'version',
    'status',
    'data_snapshot',
    'rendered_body',
    'template_hash',
    'generated_by',
    'token',
    'token_expires_at',
    'signed_at',
    'signed_by_applicant',
    'file_path',
    'signature_path',
    'superseded_at',
    'superseded_by_contract_id',
])]
class ApplicationContract extends Model
{
    use HasFactory;
    use HasStates;

    /**
     * Fields frozen at generation. The snapshot/body/hash are the authoritative record of what
     * the signer saw, and identity/version/generator never change for a given version. Only the
     * lifecycle fields (token, signing artifacts, status, supersession linkage) may move
     * afterwards, through signing/supersession/cancellation.
     *
     * @var list<string>
     */
    private const IMMUTABLE_AFTER_CREATION = [
        'application_id',
        'version',
        'data_snapshot',
        'rendered_body',
        'template_hash',
        'generated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContractState::class,
            'data_snapshot' => 'array',
            'token_expires_at' => 'datetime',
            'signed_at' => 'datetime',
            'signed_by_applicant' => 'boolean',
            'superseded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $contract) {
            foreach (self::IMMUTABLE_AFTER_CREATION as $column) {
                if ($contract->isDirty($column)) {
                    throw ContractImmutabilityException::field($contract, $column);
                }
            }
        });

        // History is never erased through the model. The application delete cascade is a
        // DB-level ON DELETE CASCADE and does not fire this event, so removing an application
        // still removes its versions atomically.
        static::deleting(function () {
            throw ContractImmutabilityException::deletionForbidden();
        });
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_contract_id');
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

    /**
     * Resolve a browser-usable URL for the stored signed artifact, tolerating both the
     * versioned representation (public-disk-relative path) and legacy absolute URLs.
     */
    public function signedFileUrl(): ?string
    {
        if ($this->file_path === null) {
            return null;
        }

        if (Str::startsWith($this->file_path, ['http://', 'https://', '/storage/'])) {
            return $this->file_path;
        }

        return Storage::disk('public')->url($this->file_path);
    }
}
