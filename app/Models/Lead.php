<?php

namespace App\Models;

use App\Enums\LeadSource;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    protected $fillable = [
        'phone',
        'data',
        'source',
        'status',
        'converted_student_id',
        'converted_at',
        'last_contacted_at',
    ];

    protected $casts = [
        'data' => 'array',
        'source' => LeadSource::class,
        'converted_at' => 'datetime',
        'last_contacted_at' => 'datetime',
    ];

    public function scopeFilter($query, $filters)
    {
        $allowed = ['status', 'source', 'phone'];

        foreach ($filters as $key => $value) {
            if (in_array($key, $allowed)) {
                $query->where($key, 'like', '%' . $value . '%');
            }
        }
    }
}
