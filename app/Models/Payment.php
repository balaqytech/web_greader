<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentPurpose;
use App\Models\Scopes\BranchScope;
use App\States\Payments\Paid;
use App\States\Payments\PaymentState;
use App\Support\Model;
use App\Support\Money\OmrAmount;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Spatie\ModelStates\HasStates;

/**
 * A single registration-fee payment attempt.
 *
 * One row per attempt, never per application. A failed, rejected or expired attempt stays on
 * the record and a retry creates a new row, so the history of what was tried is never
 * overwritten.
 *
 * Two invariants are enforced by locking rather than by database constraints — see the
 * migration for why:
 *
 *   - at most one *active* (non-terminal) attempt per application at a time;
 *   - at most one *paid* attempt per application, ever.
 *
 * **Lock order is always application first, payment second.** Every path that can change a
 * payment's state also potentially advances the application, so a consistent order is what
 * keeps two concurrent attempts from deadlocking against each other. Any new caller must
 * follow it.
 *
 * Auditing comes from the base model: every write here is financial evidence.
 *
 * @property string $reference
 * @property string $amount Exact decimal string — never a float. Use `money()`.
 * @property PaymentMethod $method
 * @property PaymentPurpose $purpose
 * @property PaymentState $status
 */
#[ScopedBy(BranchScope::class)]
#[Fillable([
    'application_id',
    'branch_id',
    'purpose',
    'method',
    'status',
    'amount',
    'currency',
    'idempotency_key',
    'request_hash',
    'provider_session_id',
    'provider_checkout_url',
    'provider_expires_at',
    'provider_payload',
    'receipt_path',
    'receipt_idempotency_key',
    'receipt_request_hash',
    'failure_reason',
    'rejection_reason',
    'verified_by',
    'verified_at',
    'cash_reference',
    'cash_notes',
    'created_by',
])]
class Payment extends Model
{
    use HasFactory;
    use HasStates;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => PaymentPurpose::class,
            'method' => PaymentMethod::class,
            'status' => PaymentState::class,
            // decimal:3 keeps this a string at every boundary. A float cast would silently
            // reintroduce binary rounding into money that must reconcile to the baisa.
            'amount' => 'decimal:3',
            'provider_payload' => 'array',
            'provider_expires_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $payment) {
            // Lowercase to match Laravel's own HasUlids convention.
            $payment->reference ??= strtolower((string) Str::ulid());
        });
    }

    /**
     * The route key is the public ULID, never the auto-increment id: an implicit binding on
     * `id` would let a caller probe for other applications' payments by counting upwards.
     */
    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /**
     * The amount as an exact value object. Prefer this over the raw attribute anywhere
     * arithmetic or a provider conversion is involved.
     */
    public function money(): OmrAmount
    {
        return OmrAmount::fromString($this->amount);
    }

    public function isPaid(): bool
    {
        return $this->status instanceof Paid;
    }

    public function safeCheckoutUrl(): ?string
    {
        $url = $this->provider_checkout_url;

        if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https' ? $url : null;
    }

    /**
     * Attempts that may still become paid. Reuses PaymentState's own definition rather than
     * restating the state list, so this SQL rule and `PaymentState::isActive()` cannot drift.
     */
    public function scopeActive($query): void
    {
        $query->whereState('status', PaymentState::activeStates());
    }

    public function scopeTerminal($query): void
    {
        $query->whereState('status', PaymentState::terminalStates());
    }

    public function scopePaid($query): void
    {
        $query->whereState('status', Paid::class);
    }

    public function scopeForRegistrationFee($query): void
    {
        $query->where('purpose', PaymentPurpose::REGISTRATION_FEE);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * The central-finance user behind a verify-or-reject decision on a bank receipt.
     * Nullable — untouched for methods that never pass through verification.
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Nullable: an attempt initiated through the chatbot API is created by a service token,
     * which has no user behind it.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
