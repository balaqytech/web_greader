@props([
    'status',
])

@if ($status)
    <div {{ $attributes->merge(['class' => 'font-medium text-sm bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 p-4 rounded-lg']) }}>
        {{ $status }}
    </div>
@endif
