<?php

namespace App\Models;

use App\Enums\ProgramType;
use Database\Factories\SeasonFactory;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'type', 'start_date', 'end_date', 'is_active', 'is_registration_open', 'closed_at'])]
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
            'is_registration_open' => 'boolean',
            'closed_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeRegistrationOpen(Builder $query): Builder
    {
        return $query->where('is_registration_open', true);
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('is_closed', true);
    }
}
