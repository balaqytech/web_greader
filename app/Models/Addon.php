<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Addon extends Model
{
    protected $fillable = [
        'name',
        'description',
        'price',
        'type',
        'is_active',
    ];

    protected $casts = [
        'type' => \App\Enums\AddonType::class,
        'price' => \App\Casts\AmountCast::class,
        'is_active' => 'boolean',
    ];

    public function enrollments()
    {
        return $this->belongsToMany(Enrollment::class, 'enrollment_addon');
    }
}