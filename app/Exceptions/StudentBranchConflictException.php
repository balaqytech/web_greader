<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown during acceptance when an existing student (matched by civil number) belongs to a
 * different branch than the application being accepted. Until a student-transfer policy
 * exists, acceptance must not silently move a student between branches.
 */
class StudentBranchConflictException extends RuntimeException
{
    public static function make(string $civilNumber, int $existingBranchId, int $applicationBranchId): self
    {
        return new self(
            "Student with civil number {$civilNumber} already belongs to branch {$existingBranchId} "
            ."and cannot be accepted into branch {$applicationBranchId} until a transfer policy exists."
        );
    }
}
