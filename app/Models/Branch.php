<?php

namespace App\Models;

use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'address', 'governorate', 'phone', 'mobile', 'is_active', 'additional_info'])]
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory;

    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'additional_info' => 'array',
        ];
    }

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'program_branch');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeGovernorate($query, string $governorate)
    {
        return $query->where('governorate', $governorate);
    }
}
