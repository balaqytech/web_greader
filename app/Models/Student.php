<?php

namespace App\Models;

use App\Enums\StudentStatus;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Student extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'parent_account_id',
        'branch_id',
        'name',
        'gender',
        'date_of_birth',
        'civil_number',
        'state',
        'province',
        'village',
        'house_number',
        'block_number',
        'category',
        'parents_relationship',
        'status',
        'is_active',
        'additional_info',
    ];

    protected $casts = [
        'gender' => \App\Enums\Gender::class,
        'is_active' => 'boolean',
        'status' => \App\Enums\StudentStatus::class,
        'date_of_birth' => 'date',
        'additional_info' => 'array',
    ];

    public function parentAccount()
    {
        return $this->belongsTo(ParentAccount::class);
    }

    public function programEnrollments()
    {
        return $this->hasMany(ProgramEnrollment::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function getAgeAttribute()
    {
        return now()->diffInYears($this->date_of_birth);
    }
}
