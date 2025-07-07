<?php

namespace App\Models;

use App\Enums\StudentStatus;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $fillable = [
        'parent_account_id',
        'name',
        'gender',
        'date_of_birth',
        'status',
        'is_active',
        'additional_info',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'status' => \App\Enums\StudentStatus::class,
        'date_of_birth' => 'date',
        'additional_info' => 'array',
    ];

    public function parentAccount()
    {
        return $this->belongsTo(ParentAccount::class);
    }

    public function getAgeAttribute()
    {
        return now()->diffInYears($this->date_of_birth);
    }
}
