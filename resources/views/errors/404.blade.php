<x-app-layout>
    <x-slot name="title">{{ __('errors.404.title') }}</x-slot>
    <main class="flex flex-col items-center justify-center min-h-screen text-center">
        <h1 class="text-9xl font-bold text-gr-blue mb-4">404</h1>
        <h2 class="text-2xl font-semibold mb-2">{{ __('errors.404.title') }}</h2>
        <p class="mb-6 text-lg">{{ __('errors.404.message') }}</p>
        <x-primary-button href="{{ url('/') }}">{{ __('errors.back_to_home') }}</x-primary-button>
    </main>
</x-app-layout>
