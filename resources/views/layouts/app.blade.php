<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $title ?? env('APP_NAME') }}</title>
    <link rel="shortcut icon" href="{{ asset('logo.png') }}" type="image/x-icon">

    <!-- Fonts -->
    <link rel="stylesheet" href="{{ asset('css/filament/fonts.css') }}">

    <!-- Styles / Scripts -->
    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
    @filamentStyles
    @vite('resources/css/app.css')
</head>

<body class="flex min-h-screen flex-col text-slate-600">

    {{ $slot }}

    @livewire('notifications')
    @filamentScripts
    @vite('resources/js/app.js')
</body>

</html>
