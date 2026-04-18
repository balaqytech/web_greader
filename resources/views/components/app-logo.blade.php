@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-16 items-center justify-center rounded-md">
            <x-app-logo-icon class="size-16 fill-current text-white" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-16 items-center justify-center rounded-md">
            <x-app-logo-icon class="size-16 fill-current text-white" />
        </x-slot>
    </flux:brand>
@endif
