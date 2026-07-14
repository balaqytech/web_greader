<?php

namespace App\Exceptions;

use App\Models\Application;
use RuntimeException;

/**
 * Thrown when a state-changing operation discovers, after locking and reloading the row,
 * that the application is no longer in the state the caller assumed. This defeats stale
 * replays: a second request holding an out-of-date model instance cannot overwrite
 * artifacts, duplicate activity, or repeat a terminal transition.
 */
class StaleApplicationStateException extends RuntimeException
{
    public static function make(Application $application, string $expectedState, object $actualState): self
    {
        return new self(sprintf(
            'Application %s is no longer in %s (currently %s); the operation was aborted.',
            $application->getKey(),
            class_basename($expectedState),
            class_basename($actualState),
        ));
    }
}
