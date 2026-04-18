<?php

namespace App\Models;

use App\Enums\ProgramType;
use App\States\Leads\LeadState;
use App\Traits\HasAffiliate;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\ModelStates\HasStates;

#[Fillable(['ref_no', 'guardian_name', 'student_name', 'whatsapp', 'branch_id', 'season_id', 'program_type', 'program_id', 'data', 'status', 'source', 'affiliate_id', 'affiliate_code_snapshot'])]
class Lead extends Model
{
    use HasAffiliate;

    /** @use HasFactory<LeadFactory> */
    use HasFactory;

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
        ];
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
