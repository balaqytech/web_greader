<?php

namespace App\Models;

use App\Enums\AffiliateCategory;
use App\Enums\Source;
use App\States\Affiliates\AffiliateState;
use App\States\Affiliates\Pending;
use App\States\Affiliates\Rejected;
use App\States\Affiliates\Verified;
use App\Traits\HasWhatsapp;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

#[Fillable(['name', 'code', 'category', 'whatsapp', 'password', 'email', 'status', 'notes', 'verified_by', 'verified_at', 'rejected_by', 'rejected_at', 'creation_source'])]
class Affiliate extends Authenticatable
{
    use HasFactory;
    use HasWhatsapp;

    protected $attributes = [
        'status' => 'pending',
        'creation_source' => 'website',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'category' => AffiliateCategory::class,
            'status' => AffiliateState::class,
            'verified_at' => 'datetime',
            'rejected_at' => 'datetime',
            'creation_source' => Source::class,
        ];
    }

    protected function password(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => Hash::make($value),
        );
    }

    /**
     * Get the user's initials.
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function scopeVerified($query)
    {
        return $query->where('status', Verified::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', Pending::class);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', Rejected::class);
    }

    public static function generateUniqueCode(string $name): string
    {
        $prefix = strtoupper(Str::substr(Str::slug($name), 0, 3));

        do {
            $code = $prefix.rand(100, 999);
        } while (self::where('code', $code)->exists());

        return $code;
    }

    public function isVerified(): bool
    {
        return $this->status->equals(Verified::class);
    }

    public function isPending(): bool
    {
        return $this->status->equals(Pending::class);
    }

    public function isRejected(): bool
    {
        return $this->status->equals(Rejected::class);
    }
}
