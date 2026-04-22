<?php

namespace App\Models;

use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['program_id', 'type', 'value'])]
class ProgramRule extends Model
{
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
}
