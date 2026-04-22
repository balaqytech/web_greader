<?php

namespace App\Models;

use App\Enums\ProgramType;
use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['type', 'name', 'description', 'accept_installments', 'min_birth_date', 'max_birth_date', 'contract', 'is_open', 'is_active', 'sort_order'])]
class Program extends Model
{
    use HasFactory;

    protected $attributes = [
        'sort_order' => 0,
        'is_active' => true,
        'is_open' => true,
        'accept_installments' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProgramType::class,
            'accept_installments' => 'boolean',
            'min_birth_date' => 'date',
            'max_birth_date' => 'date',
            'is_open' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function programRules(): HasMany
    {
        return $this->hasMany(ProgramRule::class);
    }

    public function isAvailableIn(Branch $branch): bool
    {
        return $this->branches()->wherePivot('branch_id', $branch->id)->exists();
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'program_branch')->withPivot('price');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOpen($query)
    {
        return $query->active()->where('is_open', true);
    }
}
