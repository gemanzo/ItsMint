<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch, reactive } from 'vue';

const props = defineProps({
    blueprints: Object, // Expect blueprints to have latest_price eager loaded or attached
    filters: {
        type: Object,
        default: () => ({ 
            category_id: null, 
            expansion_id: null // Added expansion_id to default filters
        })
    },
    availableCategories: Array, // Added availableCategories prop
    availableExpansions: Array, // Added availableExpansions prop
});

// Reactive object for filters
const currentFilters = reactive({
    category: props.filters.category_id || '',
    expansion: props.filters.expansion_id || '',
});

const formatPrice = (cents, currency = 'EUR') => {
    if (cents === null || cents === undefined) return 'N/A';
    return (cents / 100).toLocaleString('it-IT', { style: 'currency', currency: currency });
};

// Watch for changes in any filter and reload page
watch(currentFilters, (newFilters) => {
    router.get(route('dashboard.blueprints.index'), {
        category_id: newFilters.category || undefined,
        expansion_id: newFilters.expansion || undefined, // Send expansion_id
    }, {
        preserveState: true,
        replace: true,
    });
}, { deep: true }); // Use deep watch for object

</script>

<template>
    <Head title="Blueprints Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Blueprints</h2>
                <div class="flex space-x-4 items-center">
                    <!-- Category Filter Dropdown -->
                    <div>
                        <label for="category_filter" class="sr-only">Filter by Category</label>
                        <select 
                            id="category_filter" 
                            v-model="currentFilters.category"
                            class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md"
                        >
                            <option value="">All Categories</option>
                            <option v-for="category in availableCategories" :key="category.id" :value="category.id">
                                {{ category.name }}
                            </option>
                        </select>
                    </div>
                    <!-- Expansion Filter Dropdown -->
                    <div>
                        <label for="expansion_filter" class="sr-only">Filter by Expansion</label>
                        <select 
                            id="expansion_filter" 
                            v-model="currentFilters.expansion"
                            class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md"
                        >
                            <option value="">All Expansions</option>
                            <option v-for="expansion in availableExpansions" :key="expansion.id" :value="expansion.id">
                                {{ expansion.name }}
                            </option>
                        </select>
                    </div>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <div v-if="blueprints.data.length > 0" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                            <Link 
                                v-for="blueprint in blueprints.data" 
                                :key="blueprint.id" 
                                :href="route('dashboard.blueprints.show', blueprint.id)" 
                                class="border rounded-lg p-4 shadow-lg dark:border-gray-700 flex flex-col justify-between hover:shadow-xl transition-shadow duration-300 ease-in-out"
                            >
                                <div>
                                    <img 
                                        :src="blueprint.image_url ? blueprint.image_url : 'https://via.placeholder.com/300x420.png?text=No+Image'" 
                                        :alt="blueprint.name"
                                        class="w-full h-auto object-contain rounded-md mb-4 max-h-80"
                                        @error="(event) => event.target.src='https://via.placeholder.com/300x420.png?text=No+Image+Available'"
                                    >
                                    <h3 class="text-lg font-semibold mb-1 truncate" :title="blueprint.name">{{ blueprint.name }}</h3>
                                    <p v-if="blueprint.version" class="text-sm text-gray-500 dark:text-gray-400 mb-1">Version: {{ blueprint.version }}</p>
                                    <p v-if="blueprint.expansion" class="text-sm text-gray-600 dark:text-gray-400 mb-2">Expansion: {{ blueprint.expansion.name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-500 mb-2">ID: {{ blueprint.ct_id }}</p>
                                </div>
                                
                                <div class="mt-auto pt-2 border-t dark:border-gray-700">
                                    <div v-if="blueprint.latest_price">
                                        <p class="text-lg font-bold text-green-600 dark:text-green-400">
                                            {{ formatPrice(blueprint.latest_price.price_cents, blueprint.latest_price.currency) }}
                                        </p>
                                        <div class="text-xs text-gray-400 dark:text-gray-500 mt-1 space-y-0.5">
                                            <p><span class="font-medium">Condition:</span> {{ blueprint.latest_price.condition }}</p>
                                            <p><span class="font-medium">Language:</span> {{ blueprint.latest_price.language.toUpperCase() }}</p>
                                            <p><span class="font-medium">Foil:</span> {{ blueprint.latest_price.is_foil ? 'Yes' : 'No' }}</p>
                                        </div>
                                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1.5">
                                            Updated: {{ blueprint.latest_price.fetched_at ? new Date(blueprint.latest_price.fetched_at).toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' }) : 'N/A' }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-600 mt-0.5">Source: {{ blueprint.latest_price.source }}</p>
                                    </div>
                                    <div v-else class="text-sm text-gray-400 dark:text-gray-500">
                                        No price data available.
                                    </div>
                                </div>
                            </Link>
                        </div>
                        <div v-else>
                            <p>No blueprints found.</p>
                        </div>

                        <!-- Pagination -->
                        <div v-if="blueprints.links.length > 3" class="mt-8 flex justify-center">
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                <template v-for="(link, key) in blueprints.links" :key="key">
                                    <div v-if="link.url === null"
                                         class="relative inline-flex items-center px-4 py-2 border text-sm font-medium bg-gray-100 border-gray-300 text-gray-400 cursor-not-allowed dark:bg-gray-700 dark:border-gray-600 dark:text-gray-500"
                                         v-html="link.label"
                                    />
                                    <Link v-else
                                          :href="link.url"
                                          v-html="link.label"
                                          class="relative inline-flex items-center px-4 py-2 border text-sm font-medium"
                                          :class="{
                                              'z-10 bg-indigo-50 border-indigo-500 text-indigo-600 dark:bg-indigo-900 dark:border-indigo-700 dark:text-indigo-300': link.active,
                                              'bg-white border-gray-300 text-gray-500 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-700': !link.active
                                          }"
                                    />
                                </template>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template> 