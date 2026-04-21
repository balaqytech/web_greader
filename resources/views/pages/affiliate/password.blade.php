<x-layouts::affiliate.header :title="__('affiliate.password.title')">
    <flux:main container>
        <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
            <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('affiliate.password.title') }}</flux:heading>
                <flux:subheading>{{ __('affiliate.password.description') }}</flux:subheading>

                <!-- Session Status -->
                @if (session('status'))
                    <div class="mt-4 rounded-lg bg-green-100 p-3 text-sm text-green-600 dark:bg-green-900/30 dark:text-green-400">
                        {{ session('status') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('affiliate.password.update') }}" class="mt-6 space-y-6">
                    @csrf
                    @method('PUT')

                    <!-- Current Password -->
                    <flux:input
                        name="current_password"
                        :label="__('affiliate.password.current_password')"
                        type="password"
                        required
                        autocomplete="current-password"
                        :placeholder="__('affiliate.password.current_password_placeholder')"
                        viewable
                    />

                    <!-- New Password -->
                    <flux:input
                        name="password"
                        :label="__('affiliate.password.new_password')"
                        type="password"
                        required
                        autocomplete="new-password"
                        :placeholder="__('affiliate.password.new_password_placeholder')"
                        viewable
                    />

                    <!-- Confirm Password -->
                    <flux:input
                        name="password_confirmation"
                        :label="__('affiliate.password.confirm_password')"
                        type="password"
                        required
                        autocomplete="new-password"
                        :placeholder="__('affiliate.password.confirm_password_placeholder')"
                        viewable
                    />

                    <div class="flex items-center gap-4">
                        <flux:button variant="primary" type="submit" data-test="update-affiliate-password-button">
                            {{ __('affiliate.password.save') }}
                        </flux:button>
                    </div>
                </form>
            </div>
        </div>
    </flux:main>
</x-layouts::affiliate.header>
