<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown during acceptance when the acting guardian cannot be persisted because their
 * identity number or phone already belongs to a different guardian. It is a deliberate
 * domain error (with a translated message) rather than a raw database integrity error, and
 * because it is raised inside the acceptance transaction it leaves no partial writes.
 */
class GuardianConflictException extends RuntimeException
{
    public static function phone(string $phone): self
    {
        return new self(__('alerts.application.guardian_phone_conflict', ['phone' => $phone]));
    }

    public static function identity(string $idNumber): self
    {
        return new self(__('alerts.application.guardian_identity_conflict', ['id_number' => $idNumber]));
    }
}
