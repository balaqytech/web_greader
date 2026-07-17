<?php

declare(strict_types=1);

namespace App\States\Documents;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class DocumentState extends State
{
    abstract public function getLabel(): string;

    abstract public function getColor(): string;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Missing::class)
            ->allowTransition(Missing::class, Uploaded::class)
            ->allowTransition(Uploaded::class, Approved::class)
            ->allowTransition(Uploaded::class, Rejected::class)
            ->allowTransition(Uploaded::class, Uploaded::class)
            ->allowTransition(Rejected::class, Uploaded::class)
            ->allowTransition(Approved::class, Uploaded::class);
    }
}
