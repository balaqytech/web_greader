<?php

namespace App\States\Applications\Transitions;

use App\Actions\Applications\RecordApplicationActivityAction;
use App\Exceptions\ApplicationIncompleteException;
use App\Models\Application;
use App\States\Applications\Cancelled;
use App\Support\Applications\LockApplication;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Transition;

/**
 * Shared behaviour for every cancellation edge: a nonblank note is required, the row is
 * locked and its persisted state re-verified before writing (defeating stale replays), and
 * the state change plus activity are written in one transaction so nothing partial remains.
 */
abstract class CancelApplicationTransition extends Transition
{
    public function __construct(
        public Application $application,
        public ?string $notes = null,
    ) {}

    /**
     * @return class-string
     */
    abstract protected function fromState(): string;

    public function handle(): Application
    {
        return DB::transaction(function () {
            if (blank($this->notes)) {
                throw new ApplicationIncompleteException(__('alerts.application.cancellation_note_required'));
            }

            $application = LockApplication::inState($this->application, $this->fromState());

            $application->status = Cancelled::class;
            $application->save();

            app(RecordApplicationActivityAction::class)->handle(
                $application,
                ($this->fromState())::$name,
                Cancelled::$name,
                $this->notes,
            );

            return $application;
        }, attempts: 3);
    }
}
