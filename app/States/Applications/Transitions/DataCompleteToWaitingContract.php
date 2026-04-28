<?php

namespace App\States\Applications\Transitions;

use App\Models\Application;
use App\States\Applications\WaitingContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\ModelStates\Transition;

class DataCompleteToWaitingContract extends Transition
{
    public function __construct(
        private readonly Application $application,
        private readonly ?int $transitionedBy = null,
        private readonly ?string $notes = null,
    ) {}

    public function handle(): Application
    {
        $fromState = $this->application->status::$name;

        DB::transaction(function () use ($fromState) {
            $this->application->forceFill([
                'status' => WaitingContract::$name,
                'contract_token' => Str::uuid()->toString(),
                'contract_token_expires_at' => now()->addDays(7),
            ])->save();

            $this->application->activities()->create([
                'transitioned_by' => $this->transitionedBy,
                'from_state' => $fromState,
                'to_state' => WaitingContract::$name,
                'notes' => $this->notes,
                'transitioned_at' => now(),
            ]);
        });

        return $this->application->fresh();
    }
}
