<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    blueprint: Object, // Expect blueprint to have expansion and latest_price eager loaded
});

const formatPrice = (cents, currency = 'EUR') => {
    if (cents === null || cents === undefined) return 'N/A';
    return (cents / 100).toLocaleString('it-IT', { style: 'currency', currency: currency });
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleString('it-IT', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<template>
    <Head :title="`Blueprint - ${blueprint.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ blueprint.name }}
                </h2>
                <Link :href="route('dashboard.blueprints.index')" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                    &laquo; Back to Blueprints
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <!-- Left Column: Image -->
                            <div class="md:col-span-1">
                                <img 
                                    :src="blueprint.image_url ? blueprint.image_url : 'https://via.placeholder.com/300x420.png?text=No+Image'" 
                                    :alt="blueprint.name"
                                    class="w-full h-auto object-contain rounded-md shadow-lg max-h-[70vh]"
                                    @error="(event) => event.target.src='https://via.placeholder.com/300x420.png?text=No+Image+Available'"
                                >
                            </div>

                            <!-- Right Column: Details -->
                            <div class="md:col-span-2">
                                <h3 class="text-2xl font-semibold mb-4">{{ blueprint.name }}</h3>
                                
                                <div class="space-y-3">
                                    <div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Version</p>
                                        <p class="text-lg">{{ blueprint.version || 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Expansion</p>
                                        <p class="text-lg">{{ blueprint.expansion ? blueprint.expansion.name : 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">CardTrader ID</p>
                                        <p class="text-lg">{{ blueprint.ct_id }}</p>
                                    </div>
                                     <div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Category</p>
                                        <p class="text-lg">{{ blueprint.category ? blueprint.category.name : 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Created At</p>
                                        <p class="text-lg">{{ formatDate(blueprint.created_at) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Last Updated</p>
                                        <p class="text-lg">{{ formatDate(blueprint.updated_at) }}</p>
                                    </div>
                                </div>

                                <div v-if="blueprint.latest_price" class="mt-6 pt-6 border-t dark:border-gray-700">
                                    <h4 class="text-xl font-semibold mb-3">Latest Price Information</h4>
                                    <div class="space-y-2">
                                        <div>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">Price</p>
                                            <p class="text-2xl font-bold text-green-600 dark:text-green-400">
                                                {{ formatPrice(blueprint.latest_price.price_cents, blueprint.latest_price.currency) }}
                                            </p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">Details</p>
                                            <div class="text-md space-y-0.5">
                                                <p><span class="font-medium">Condition:</span> {{ blueprint.latest_price.condition }}</p>
                                                <p><span class="font-medium">Language:</span> {{ blueprint.latest_price.language.toUpperCase() }}</p>
                                                <p><span class="font-medium">Foil:</span> {{ blueprint.latest_price.is_foil ? 'Yes' : 'No' }}</p>
                                            </div>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">Source</p>
                                            <p class="text-md">{{ blueprint.latest_price.source }}</p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">Price Fetched At</p>
                                            <p class="text-md">{{ formatDate(blueprint.latest_price.fetched_at) }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="mt-6 pt-4 border-t dark:border-gray-700">
                                    <p class="text-lg text-gray-500 dark:text-gray-400">No price data available for this blueprint.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template> 