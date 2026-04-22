<?php

namespace App\Models;

use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['application_id', 'transitioned_by', 'from_state', 'to_state', 'notes', 'transitioned_at'])]
class ApplicationActivity extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'transitioned_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * The user who triggered the transition. Nullable — system-triggered transitions
     * (e.g. auto-conversion from lead) will have no user.
     */
    public function transitionedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transitioned_by');
    }
}
