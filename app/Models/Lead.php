<?php

namespace App\Models;

use App\Enums\ProgramType;
use App\Enums\Source;
use App\Models\Scopes\BranchScope;
use App\States\Leads\LeadState;
use App\Support\LeadRefNoGenerator;
use App\Support\Model;
use App\Traits\HasAffiliate;
use App\Traits\HasNormalizedStudentName;
use App\Traits\HasWhatsapp;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\ModelStates\HasStates;

#[ScopedBy(BranchScope::class)]
#[Fillable(['ref_no', 'guardian_name', 'student_name', 'student_name_normalized', 'identity_fingerprint', 'whatsapp', 'mother_phone', 'branch_id', 'season_id', 'program_type', 'program_id', 'data', 'status', 'source', 'affiliate_id', 'affiliate_code_snapshot'])]
class Lead extends Model
{
    use HasAffiliate;
    use HasFactory;
    use HasNormalizedStudentName;
    use HasStates;
    use HasWhatsapp;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'program_type' => ProgramType::class,
            'status' => LeadState::class,
            'data' => 'array',
            'source' => Source::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $lead): void {
            if (filled($lead->ref_no)) {
                return;
            }

            $lead->ref_no = app(LeadRefNoGenerator::class)->generate();
        });
    }

    public function setDataAttribute(mixed $value): void
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : null;
        }

        if (is_array($value) && array_key_exists('mother_phone', $value)) {
            $this->attributes['mother_phone'] = $value['mother_phone'] !== null
                ? (string) $value['mother_phone']
                : null;

            unset($value['mother_phone']);
        }

        $this->attributes['data'] = $value === null || $value === []
            ? null
            : json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    public function application(): HasOne
    {
        return $this->hasOne(Application::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(LeadContact::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function scopeFilter($query, array $filters): void
    {
        foreach ($filters as $field => $value) {
            if (is_null($value)) {
                continue;
            }

            if (str_starts_with($field, 'data.')) {
                $jsonKey = substr($field, 5);

                if ($jsonKey === 'mother_phone') {
                    $query->where('mother_phone', $value);
                } else {
                    $query->whereJsonContains("data->{$jsonKey}", $value);
                }
            } else {
                $query->where($field, $value);
            }
        }
    }

    /**
     * Search across text columns and optionally inside the data JSON column.
     *
     * Accepted formats:
     *   - ?search=foo                 — searches guardian_name, student_name, whatsapp, ref_no
     *   - ?search=foo&search_fields[]=data.mother_phone  — also searches mother_phone
     */
    public function scopeSearch($query, string $term, array $jsonKeys = []): void
    {
        $query->where(function ($q) use ($term, $jsonKeys) {
            $q->where('guardian_name', 'like', "%{$term}%")
                ->orWhere('student_name', 'like', "%{$term}%")
                ->orWhere('whatsapp', 'like', "%{$term}%")
                ->orWhere('ref_no', 'like', "%{$term}%");

            foreach ($jsonKeys as $key) {
                if ($key === 'mother_phone') {
                    $q->orWhere('mother_phone', 'like', "%{$term}%");
                } else {
                    $q->orWhere("data->{$key}", 'like', "%{$term}%");
                }
            }
        });
    }
}
