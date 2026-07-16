{{--
    Shown to a guardian returning from Thawani's hosted checkout.

    Deliberately minimal. This page is public and unauthenticated — the guardian arrives with
    no session — so it discloses only this payment's own reference and status. Never the
    application's data, the guardian's details, the amount, or any provider internals: the
    ULID in the URL is the only thing standing between this page and anyone who has it.

    The status shown is always the provider's own answer, retrieved server-side. Nothing here
    is derived from the redirect, which is client-controlled.
--}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('admin.payment.label') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-900 font-sans antialiased p-4 md:p-8 flex items-center justify-center min-h-screen">
    <div class="max-w-md w-full bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center">
        <h2 class="text-2xl font-bold text-gray-800 mb-2">{{ $status->getLabel() }}</h2>

        <p class="text-gray-600 mb-6">{{ $message }}</p>

        <p class="text-xs text-gray-400 font-mono">{{ $reference }}</p>
    </div>
</body>
</html>
