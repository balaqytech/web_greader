<?php

namespace App\Filament\Resources\Applications\Actions\Concerns;

use App\Models\Application;
use Livewire\Component;

/**
 * Application state transitions run under a row lock and return a separately-fetched
 * Application instance rather than mutating the model the action closure was invoked with
 * (see App\Support\Applications\LockApplication). Without syncing it back, the Livewire page
 * keeps rendering the pre-transition record — including a stale or still-absent `contract`
 * relation — after a successful mutation.
 */
trait RefreshesApplicationRecord
{
    protected function refreshLivewireRecord(Application $application, ?Component $livewire): void
    {
        $application->load('contract');

        if ($livewire !== null && property_exists($livewire, 'record')) {
            $livewire->record = $application;
        }
    }
}
