<template>
<div>
    <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
        <NavLink :href="route('dashboard')" :active="route().current('dashboard')">
            Dashboard
        </NavLink>
        <NavLink :href="route('dashboard.blueprints.index')" :active="route().current('dashboard.blueprints.index') || route().current('dashboard.blueprints.show')">
            Blueprints
        </NavLink>
        <NavLink :href="route('portfolios.index')" :active="route().current('portfolios.index') || route().current('portfolios.show')">
            Portfolios
        </NavLink>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }" class="sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">
                Dashboard
            </ResponsiveNavLink>
            <ResponsiveNavLink :href="route('dashboard.blueprints.index')" :active="route().current('dashboard.blueprints.index') || route().current('dashboard.blueprints.show')">
                Blueprints
            </ResponsiveNavLink>
            <ResponsiveNavLink :href="route('portfolios.index')" :active="route().current('portfolios.index') || route().current('portfolios.show')">
                Portfolios
            </ResponsiveNavLink>
        </div>

        <!-- Responsive Settings Options -->
        <!-- QUI CI VA IL BLOCCO COPIATO DA AuthenticatedLayout.vue se necessario -->
        <!-- Esempio:
        <div class="pt-4 pb-1 border-t border-gray-200 dark:border-gray-600">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800 dark:text-gray-200">{{ $page.props.auth.user.name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ $page.props.auth.user.email }}</div>
            </div>
            <div class="mt-3 space-y-1">
                <ResponsiveNavLink :href="route('profile.edit')"> Profile </ResponsiveNavLink>
                <ResponsiveNavLink :href="route('logout')" method="post" as="button">
                    Log Out
                </ResponsiveNavLink>
            </div>
        </div>
        -->
    </div>
</div>
</template>

<script setup lang="ts">
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';

defineProps<{
    showingNavigationDropdown: boolean;
}>();
</script> 