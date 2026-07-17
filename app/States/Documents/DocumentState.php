<?php

declare(strict_types=1);

namespace App\States\Documents;

use App\States\Documents\Transitions\ToUploaded;
use App\States\Documents\Transitions\UploadedToApproved;
use App\States\Documents\Transitions\UploadedToRejected;
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
            ->allowTransition(Missing::class, Uploaded::class, ToUploaded::class)
            ->allowTransition(Uploaded::class, Approved::class, UploadedToApproved::class)
            ->allowTransition(Uploaded::class, Rejected::class, UploadedToRejected::class)
            ->allowTransition(Uploaded::class, Uploaded::class, ToUploaded::class)
            ->allowTransition(Rejected::class, Uploaded::class, ToUploaded::class)
            ->allowTransition(Approved::class, Uploaded::class, ToUploaded::class);
    }
}
