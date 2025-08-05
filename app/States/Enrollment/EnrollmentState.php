<?php

namespace App\States\Enrollment;

use App\Models\ProgramEnrollment;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class EnrollmentState extends State
{
    abstract public function label(): string;

    abstract public function color(): string;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Draft::class)
            ->allowTransition(Draft::class, Pending::class)
            ->allowTransition(Draft::class, Canceled::class)
            ->allowTransition(Pending::class, Signed::class)
            ->allowTransition(Pending::class, Rejected::class)
            ->allowTransition(Signed::class, Approved::class)
            ->allowTransition(Signed::class, Rejected::class)
            ->allowTransition(Approved::class, Completed::class)
            ->allowTransition(Approved::class, Rejected::class);
    }
} 