<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

final class PaymentReceiptStorage
{
    public function exists(string $path): bool
    {
        return $path !== '' && Storage::disk('local')->exists($path);
    }

    /**
     * Removes only an upload that no persisted payment references.
     */
    public function deleteIfUnreferenced(string $path): void
    {
        if ($path === '' || Payment::withoutGlobalScopes()->where('receipt_path', $path)->exists()) {
            return;
        }

        if (! Storage::disk('local')->delete($path)) {
            Log::warning('An unreferenced payment receipt could not be removed after a failed transition.', [
                'receipt_path' => $path,
            ]);
        }
    }
}
