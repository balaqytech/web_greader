@php
    $affiliate = auth('affiliate')->user();
    $leadsCount = $affiliate->leads()->count();
@endphp

<x-layouts::affiliate.header :title="__('affiliate.dashboard.title')">
    <flux:main container>
        <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
            <!-- Stats Widget -->
            <div class="grid gap-4 md:grid-cols-3">
                <div class="flex flex-col gap-2 rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                    <div class="flex items-center gap-3">
                        <div class="flex size-10 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/30">
                            <flux:icon name="users" class="size-5 text-blue-600 dark:text-blue-400" />
                        </div>
                        <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('affiliate.dashboard.total_leads') }}</flux:heading>
                    </div>
                    <flux:heading size="xl" class="mt-2 text-3xl font-bold">{{ number_format($leadsCount) }}</flux:heading>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('affiliate.dashboard.all_leads_referred_by_you') }}</flux:text>
                </div>
            </div>

            <!-- Welcome Section -->
            <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('affiliate.dashboard.greeting', ['name' => $affiliate->name]) }}</flux:heading>
                <flux:text class="mt-2 text-zinc-500 dark:text-zinc-400">
                    {{ __('affiliate.dashboard.your_affiliate_code_is') }}
                    <span class="font-mono font-semibold text-zinc-900 dark:text-zinc-100">{{ $affiliate->code ?? '—' }}</span>
                </flux:text>
                <flux:text class="mt-2 text-zinc-500 dark:text-zinc-400">
                    {{ __('affiliate.dashboard.your_affiliate_link_is') }}
                    <a href="{{ 'https://g-reader-school.com/?ref=' . $affiliate->code ?? '#' }}" target="_blank" class="font-mono font-semibold text-zinc-900 dark:text-zinc-100 hover:underline hover:text-accent-content">{{ 'https://g-reader-school.com/?ref=' . $affiliate->code ?? '—' }}</a>
                </flux:text>
            </div>
        </div>
    </flux:main>
</x-layouts::affiliate.header>