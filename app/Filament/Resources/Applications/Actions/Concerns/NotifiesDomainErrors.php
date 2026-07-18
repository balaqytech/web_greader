<?php

namespace App\Filament\Resources\Applications\Actions\Concerns;

use App\Exceptions\ApplicationIncompleteException;
use App\Exceptions\CorrectionException;
use App\Exceptions\StaleApplicationStateException;
use Filament\Notifications\Notification;
use Throwable;

/**
 * Renders a failed domain action as a user-facing notification without leaking internals.
 *
 * Expected domain outcomes carry a translated message: CorrectionException exposes a
 * `translationKey`, ApplicationIncompleteException is constructed with an already-translated
 * message, and a stale-state race is a routine concurrent outcome shown as a generic
 * "refresh and retry" message. Anything else is unexpected — it is reported for the operators
 * and shown to the user as a generic translated error, never as the raw exception message
 * (which may carry SQL or other internals). In every case the notification is a failure, so a
 * caught error can never read as a successful action.
 */
trait NotifiesDomainErrors
{
    protected function notifyDomainFailure(Throwable $e): void
    {
        $title = match (true) {
            $e instanceof CorrectionException => __($e->translationKey),
            $e instanceof ApplicationIncompleteException => $e->getMessage(),
            $e instanceof StaleApplicationStateException => __('alerts.application.state_changed_refresh'),
            default => $this->reportAndGenericMessage($e),
        };

        Notification::make()
            ->title($title)
            ->danger()
            ->send();
    }

    private function reportAndGenericMessage(Throwable $e): string
    {
        report($e);

        return __('alerts.application.action_failed');
    }
}
