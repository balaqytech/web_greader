<x-layouts::auth :title="__('affiliate.auth.register.title')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('affiliate.auth.register.title')" :description="__('affiliate.auth.register.description')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('affiliate.register.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Name -->
            <flux:input
                name="name"
                :label="__('affiliate.auth.register.name')"
                :value="old('name')"
                type="text"
                required
                autofocus
                autocomplete="name"
                :placeholder="__('affiliate.auth.register.name_placeholder')"
            />

            <!-- WhatsApp Number -->
            <flux:input
                name="whatsapp"
                :label="__('affiliate.auth.register.whatsapp')"
                :value="old('whatsapp')"
                type="text"
                required
                autocomplete="tel"
                :placeholder="__('affiliate.auth.register.whatsapp_placeholder')"
            />

            <!-- Password -->
            <flux:input
                name="password"
                :label="__('affiliate.auth.register.password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('affiliate.auth.register.password_placeholder')"
                viewable
            />

            <!-- Confirm Password -->
            <flux:input
                name="password_confirmation"
                :label="__('affiliate.auth.register.password_confirmation')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('affiliate.auth.register.password_confirmation_placeholder')"
                viewable
            />

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full" data-test="affiliate-register-button">
                    {{ __('affiliate.auth.register.register') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('affiliate.auth.register.have_account') }}</span>
            <flux:link :href="route('affiliate.login')" wire:navigate>{{ __('affiliate.auth.register.login') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
