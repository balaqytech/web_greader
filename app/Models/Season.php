<?php

namespace App\Models;

use App\Enums\ProgramType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'type', 'start_date', 'end_date', 'is_active', 'closed_at'])]
class Season extends Model
{
    /** @use HasFactory<SeasonFactory> */
    use HasFactory;

    protected $attributes = [
        'is_active' => true,
        'closed_at' => null,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProgramType::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
            'closed_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('is_closed', true);
    }

    public static function current(ProgramType $program_type): self
    {
        return self::where('type', $program_type)->where('is_active', true)->firstOrFail();
    }
}
