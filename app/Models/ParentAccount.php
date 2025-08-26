<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ParentAccount extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'civil_number',
        'occupation',
        'occupation_address',
        'occupation_phone',
        'password',
        'is_active',
        'additional_info',
        'branch_id',
    ];

    protected $casts = [
        'additional_info' => 'json',
        'is_active' => 'boolean',
    ];

    public function students()
    {
        return $this->hasMany(Student::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function programEnrollments()
    {
        return $this->hasManyThrough(ProgramEnrollment::class, Student::class);
    }

    public function getChildrenCountAttribute()
    {
        return $this->students()->count();
    }
}
