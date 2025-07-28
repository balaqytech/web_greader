<x-app-layout>
    <x-slot name="title">{{ __('errors.500.title') }}</x-slot>
    <main class="flex flex-col items-center justify-center min-h-screen text-center">
        <h1 class="text-9xl font-bold text-gr-blue mb-4">500</h1>
        <h2 class="text-2xl font-semibold mb-2">{{ __('errors.500.title') }}</h2>
        <p class="mb-6 text-lg">{{ __('errors.500.message') }}</p>
        <x-primary-button onclick="window.history.back(); return false;">{{ __('errors.go_back') }}</x-primary-button>
    </main>
</x-app-layout>
