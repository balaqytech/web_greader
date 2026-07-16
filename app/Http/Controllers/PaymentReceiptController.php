<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentReceiptController extends Controller
{
    public function __invoke(Payment $payment): StreamedResponse
    {
        Gate::authorize('viewReceipt', $payment);

        $path = $payment->receipt_path;

        abort_unless(is_string($path) && $path !== '' && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, basename($path));
    }
}
