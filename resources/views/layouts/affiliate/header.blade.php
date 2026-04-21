<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:header container class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden mr-2" icon="bars-2" inset="left" />

            <x-app-logo href="{{ route('affiliate.dashboard') }}" wire:navigate />

            <flux:navbar class="-mb-px max-lg:hidden">
                <flux:navbar.item icon="layout-grid" :href="route('affiliate.dashboard')" :current="request()->routeIs('affiliate.dashboard')" wire:navigate>
                    {{ __('affiliate.dashboard.title') }}
                </flux:navbar.item>
            </flux:navbar>

            <flux:spacer />

            <flux:dropdown position="bottom" align="start">
    <flux:sidebar.profile
        :name="auth()->user()->name"
        :initials="auth()->user()->initials()"
        icon:trailing="chevrons-up-down"
        data-test="sidebar-menu-button"
    />

    <flux:menu>
        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
            <flux:avatar
                :name="auth()->user()->name"
                :initials="auth()->user()->initials()"
            />
            <div class="grid flex-1 text-start text-sm leading-tight">
                <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                <flux:text class="truncate">{{ auth()->user()->whatsapp }}</flux:text>
            </div>
        </div>
        <flux:menu.separator />
        <flux:menu.item icon="user" :href="route('affiliate.profile.edit')" wire:navigate data-test="profile-link">
            {{ __('affiliate.profile.title') }}
        </flux:menu.item>
        <flux:menu.item icon="lock-closed" :href="route('affiliate.password.edit')" wire:navigate data-test="password-link">
            {{ __('affiliate.password.title') }}
        </flux:menu.item>
        <flux:menu.separator />
        <flux:menu.radio.group>
            <form method="POST" action="{{ route('affiliate.logout') }}" class="w-full">
                @csrf
                <flux:menu.item
                    as="button"
                    type="submit"
                    icon="arrow-right-start-on-rectangle"
                    class="w-full cursor-pointer"
                    data-test="logout-button"
                >
                    {{ __('affiliate.auth.logout.title') }}
                </flux:menu.item>
            </form>
        </flux:menu.radio.group>
    </flux:menu>
</flux:dropdown>

        </flux:header>

        <!-- Mobile Menu -->
        <flux:sidebar collapsible="mobile" sticky class="lg:hidden border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('affiliate.dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')">
                    <flux:sidebar.item icon="layout-grid" :href="route('affiliate.dashboard')" :current="request()->routeIs('affiliate.dashboard')" wire:navigate>
                        {{ __('affiliate.dashboard.title')  }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('affiliate.settings.title')">
                    <flux:sidebar.item icon="user" :href="route('affiliate.profile.edit')" :current="request()->routeIs('affiliate.profile.*')" wire:navigate>
                        {{ __('affiliate.profile.title') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="lock-closed" :href="route('affiliate.password.edit')" :current="request()->routeIs('affiliate.password.*')" wire:navigate>
                        {{ __('affiliate.password.title') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>
        </flux:sidebar>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
