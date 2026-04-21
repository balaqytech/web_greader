<?php

use App\Models\Affiliate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profile')] #[Layout('layouts.affiliate.header')] class extends Component
{
    public Affiliate $affiliate;

    public function mount()
    {
        $this->affiliate = auth('affiliate')->user();
    }
}; ?>

<flux:main container>
    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('affiliate.profile.title') }}</flux:heading>
            <flux:subheading>{{ __('affiliate.profile.description') }}</flux:subheading>

            <!-- Session Status -->
            @if (session('status'))
            <div class="mt-4 rounded-lg bg-green-100 p-3 text-sm text-green-600 dark:bg-green-900/30 dark:text-green-400">
                {{ session('status') }}
            </div>
            @endif

            <form method="POST" action="{{ route('affiliate.profile.update') }}" class="mt-6 space-y-6">
                @csrf
                @method('PUT')

                <!-- Name -->
                <flux:input
                    name="name"
                    :label="__('affiliate.profile.name')"
                    :value="old('name', $affiliate->name)"
                    type="text"
                    required
                    autofocus
                    autocomplete="name"
                    :placeholder="__('affiliate.profile.name_placeholder')" />

                <!-- WhatsApp Number -->
                <flux:input
                    name="whatsapp"
                    :label="__('affiliate.profile.whatsapp')"
                    :value="old('whatsapp', $affiliate->whatsapp)"
                    type="text"
                    required
                    autocomplete="tel"
                    :placeholder="__('affiliate.profile.whatsapp_placeholder')" />

                <!-- Email -->
                <flux:input
                    name="email"
                    :label="__('affiliate.profile.email')"
                    :value="old('email', $affiliate->email)"
                    type="email"
                    autocomplete="email"
                    :placeholder="__('affiliate.profile.email_placeholder')" />

                <div class="flex items-center gap-4">
                    <flux:button variant="primary" type="submit" data-test="update-affiliate-profile-button">
                        {{ __('affiliate.profile.save') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </div>
</flux:main>