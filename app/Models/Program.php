<?php

namespace App\Models;

use App\Enums\ProgramType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Branch;

#[Fillable(['type', 'name', 'description', 'base_price', 'accept_installments', 'contract', 'is_open', 'is_active', 'sort_order'])]
class Program extends Model
{
    use HasFactory;

    protected $attributes = [
        'sort_order' => 0,
        'is_active' => true,
        'is_open' => true,
        'accept_installments' => false,
        'base_price' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProgramType::class,
            'base_price' => 'decimal:2',
            'accept_installments' => 'boolean',
            'is_open' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'program_branch');
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
