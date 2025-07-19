<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ env('APP_NAME') }}</title>
    <link rel="shortcut icon" href="{{ asset('logo.png') }}" type="image/x-icon">

    <!-- Fonts -->
    <link rel="stylesheet" href="{{ asset('css/filament/fonts.css') }}">

    <!-- Styles / Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="flex p-6 lg:p-8 items-center lg:justify-center min-h-screen flex-col text-slate-600">
    <div
        class="flex flex-col items-center justify-center gap-4 w-full h-full transition-opacity opacity-100 duration-750 lg:grow starting:opacity-0">
        <img src="{{ asset('logo.png') }}" alt="Logo" class="w-24 h-24 mb-4">
        <h1 class="text-4xl font-bold text-slate-800">قريباً!</h1>
        <p class="text-lg mt-4">نحن نعمل على تطوير لوحة تحكم ولي الأمر. </p>
    </div>
</body>

</html>
