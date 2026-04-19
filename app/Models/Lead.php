<?php

namespace App\Models;

use App\Enums\ProgramType;
use App\Enums\Source;
use App\States\Leads\LeadState;
use App\Support\Model;
use App\Traits\HasAffiliate;
use App\Traits\HasWhatsapp;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\ModelStates\HasStates;

#[Fillable(['ref_no', 'guardian_name', 'student_name', 'whatsapp', 'branch_id', 'season_id', 'program_type', 'program_id', 'data', 'status', 'source', 'affiliate_id', 'affiliate_code_snapshot'])]
class Lead extends Model
{
    use HasAffiliate;
    use HasFactory;
    use HasWhatsapp;
    use HasStates;

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
        static::creating(function (self $lead) {
            $lead->ref_no = now()->format('Ymd') . str_pad(
                (string) (Lead::count() + 1),
                6,
                '0',
                STR_PAD_LEFT
            );
        });
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
}
