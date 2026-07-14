<?php

namespace App\States\Applications\Transitions;

use App\Actions\Applications\RecordApplicationActivityAction;
use App\Exceptions\ApplicationIncompleteException;
use App\Models\Application;
use App\States\Applications\Cancelled;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Transition;

/**
 * Shared behaviour for every cancellation edge: a nonblank note is required, and the
 * state change plus activity are written in one transaction so a blank note leaves no
 * state or audit change behind.
 */
abstract class CancelApplicationTransition extends Transition
{
    public function __construct(
        public Application $application,
        public ?string $notes = null,
    ) {}

    abstract protected function fromStateName(): string;

    public function handle(): Application
    {
        return DB::transaction(function () {
            if (blank($this->notes)) {
                throw new ApplicationIncompleteException(__('alerts.application.cancellation_note_required'));
            }

            $this->application->status = Cancelled::class;
            $this->application->save();

            app(RecordApplicationActivityAction::class)->handle(
                $this->application,
                $this->fromStateName(),
                Cancelled::$name,
                $this->notes,
            );

            return $this->application;
        });
    }
}
