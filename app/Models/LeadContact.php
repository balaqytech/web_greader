<?php

namespace App\Models;

use App\Enums\LeadContactMethod;
use App\Enums\LeadContactResult;
use Database\Factories\LeadContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['lead_id', 'contacted_by', 'contact_method', 'contact_result', 'notes', 'follow_up_at', 'contacted_at'])]
class LeadContact extends Model
{
    /** @use HasFactory<LeadContactFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contact_method' => LeadContactMethod::class,
            'contact_result' => LeadContactResult::class,
            'follow_up_at' => 'datetime',
            'contacted_at' => 'datetime',
        ];
    }

    public function contactedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contacted_by');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
