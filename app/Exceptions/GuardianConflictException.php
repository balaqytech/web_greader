<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown during acceptance when the acting guardian's phone number already belongs to a
 * different guardian. A matching id_number is never a conflict by itself — id_number is the
 * identity key, so a row found or won by id_number is the same real-world person and is
 * reused/updated instead. It is a deliberate domain error (with a translated message) rather
 * than a raw database integrity error, and because it is raised inside the acceptance
 * transaction it leaves no partial writes.
 */
class GuardianConflictException extends RuntimeException
{
    public static function phone(string $phone): self
    {
        return new self(__('alerts.application.guardian_phone_conflict', ['phone' => $phone]));
    }
}
