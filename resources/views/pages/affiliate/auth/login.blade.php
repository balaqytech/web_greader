<x-layouts::auth :title="__('affiliate.auth.login.title')">
    <div class="flex flex-col gap-6">
        <img src="{{ asset('logo.png') }}" alt="Logo" class="mx-auto h-24 w-24" />
        <x-auth-header :title="__('affiliate.auth.login.title')" :description="__('affiliate.auth.login.description')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('affiliate.login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- WhatsApp Number -->
            <flux:input
                name="whatsapp"
                :label="__('affiliate.auth.login.whatsapp')"
                :value="old('whatsapp')"
                type="text"
                required
                autofocus
                autocomplete="tel"
                placeholder="09XXXXXXXX"
            />

            <!-- Password -->
            <flux:input
                name="password"
                :label="__('affiliate.auth.login.password')"
                type="password"
                required
                autocomplete="current-password"
                :placeholder="__('affiliate.auth.login.password_placeholder')"
                viewable
            />

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('affiliate.auth.login.remember_me')" :checked="old('remember')" />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="affiliate-login-button">
                    {{ __('affiliate.auth.login.login') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
            <span>{{ __('affiliate.auth.login.dont_have_account') }}</span>
            <flux:link :href="route('affiliate.register')" wire:navigate>{{ __('affiliate.auth.login.register') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
