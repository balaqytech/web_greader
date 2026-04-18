<?php

namespace App\Models;

use App\Enums\AffiliateCategory;
use App\Enums\Source;
use App\States\Affiliates\AffiliateState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

#[Fillable(['name', 'code', 'category', 'whatsapp', 'password', 'email', 'status', 'notes', 'verified_by', 'verified_at', 'rejected_by', 'rejected_at', 'creation_source'])]
class Affiliate extends Model
{
    use HasFactory;

    protected $attributes = [
        'status' => 'pending',
        'creation_source' => 'website',
    ];

    protected $hidden = ['password'];

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

    protected function whatsapp(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => normalize_phone_number(
                convert_eastern_arabic_to_arabic($value)
            ),
        );
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

    public static function generateUniqueCode(string $name): string
    {
        $prefix = strtoupper(Str::substr(Str::slug($name), 0, 3));

        do {
            $code = $prefix.rand(100, 999);
        } while (self::where('code', $code)->exists());

        return $code;
    }
}
