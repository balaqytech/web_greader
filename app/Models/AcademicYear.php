<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicYear extends Model
{
    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'is_active',
        'additional_info',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'additional_info' => 'array',
    ];
}
