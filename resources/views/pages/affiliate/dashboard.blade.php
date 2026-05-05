<?php

use Akira\QrCode\Facades\QrCode;
use App\Models\Affiliate;
use App\States\Applications\Accepted;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.affiliate.header')] class extends Component {
    public Affiliate $affiliate;
    public int $leadsCount = 0;
    public int $approvedApplicationsCount = 0;
    public int $inProcessApplicationsCount = 0;
    public string $qr_code = '';
    public string $affiliate_url = '';

    public function render()
    {
        return $this->view()
            ->title(__('affiliate.dashboard.title'));
    }

    public function mount()
    {
        $this->affiliate = auth('affiliate')->user();
        $this->leadsCount = $this->affiliate->leads()->count();
        $this->approvedApplicationsCount = $this->affiliate->applications()->whereState('status', Accepted::class)->count();
        $this->inProcessApplicationsCount = $this->affiliate->applications()->whereNotState('status', Accepted::class)->count();
        $this->affiliate_url = 'https://g-reader-school.com/?ref=' . ($this->affiliate->code ?? '#');
        $this->qr_code = QrCode::format('svg')
            ->size(200)
            ->merge('public/logo.png', 0.2)
            ->text($this->affiliate_url);
    }
}; ?>

<section class="w-full">
    <flux:main container>
        <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
            <!-- Stats Widget -->
            <div class="grid gap-4 md:grid-cols-3">
                <div
                    class="flex flex-col gap-2 rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                    <div class="flex items-center gap-3">
                        <div class="flex size-10 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/30">
                            <flux:icon name="users" class="size-5 text-blue-600 dark:text-blue-400" />
                        </div>
                        <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">
                            {{ __('affiliate.dashboard.total_leads') }}
                        </flux:heading>
                    </div>
                    <flux:heading size="xl" class="mt-2 text-3xl font-bold">{{ number_format($leadsCount) }}
                    </flux:heading>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('affiliate.dashboard.all_leads_referred_by_you') }}
                    </flux:text>
                </div>

                <div
                    class="flex flex-col gap-2 rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex size-10 items-center justify-center rounded-lg bg-orange-50 dark:bg-orange-900/30">
                            <flux:icon name="clock" class="size-5 text-orange-600 dark:text-orange-400" />
                        </div>
                        <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">
                            {{ __('affiliate.dashboard.in_process_applications') }}
                        </flux:heading>
                    </div>
                    <flux:heading size="xl" class="mt-2 text-3xl font-bold">
                        {{ number_format($inProcessApplicationsCount) }}
                    </flux:heading>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('affiliate.dashboard.in_process_applications_description') }}
                    </flux:text>
                </div>

                <div
                    class="flex flex-col gap-2 rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex size-10 items-center justify-center rounded-lg bg-green-50 dark:bg-green-900/30">
                            <flux:icon name="check-circle" class="size-5 text-green-600 dark:text-green-400" />
                        </div>
                        <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">
                            {{ __('affiliate.dashboard.approved_applications') }}
                        </flux:heading>
                    </div>
                    <flux:heading size="xl" class="mt-2 text-3xl font-bold">
                        {{ number_format($approvedApplicationsCount) }}
                    </flux:heading>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('affiliate.dashboard.approved_applications_description') }}
                    </flux:text>
                </div>
            </div>

            <!-- Welcome Section -->
            <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('affiliate.dashboard.greeting', ['name' => $affiliate->name]) }}
                </flux:heading>
                <flux:text class="mt-2 text-zinc-500 dark:text-zinc-400">
                    {{ __('affiliate.dashboard.your_affiliate_code_is') }}
                    <span
                        class="font-mono font-semibold text-zinc-900 dark:text-zinc-100">{{ $affiliate->code ?? '—' }}</span>
                </flux:text>
                <flux:text class="mt-2 text-zinc-500 dark:text-zinc-400">
                    {{ __('affiliate.dashboard.your_affiliate_link_is') }}
                    <a href="{{ 'https://g-reader-school.com/?ref=' . $affiliate->code ?? '#' }}" target="_blank"
                        class="font-mono font-semibold text-zinc-900 dark:text-zinc-100 hover:underline hover:text-accent-content">{{ 'https://g-reader-school.com/?ref=' . $affiliate->code ?? '—' }}</a>
                </flux:text>
                <div class="mt-4 flex justify-center bg-white p-4 rounded-lg w-fit">
                    {!! $this->qr_code !!}
                </div>
                <flux:button href="{{ route('affiliate.download-qr-code') }}" variant="primary">
                    {{ __('affiliate.dashboard.download_qr_code') }}
                </flux:button>
            </div>
        </div>
    </flux:main>
</section>