<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch, reactive, computed } from 'vue';

const props = defineProps({
    blueprints: Object,
    filters: {
        type: Object,
        default: () => ({ 
            category_id: null, 
            expansion_id: null
        })
    },
    availableCategories: Array,
    availableExpansions: Array,
});

// Reactive object for filters
const currentFilters = reactive({
    category: props.filters.category_id || '',
    expansion: props.filters.expansion_id || '',
});

// Filter dropdown state
const showFilters = ref(false);
const searchQuery = ref('');

// View mode toggle
const viewMode = ref('grid'); // 'grid' or 'list'

// Placeholder image as data URL SVG
const placeholderImage = 'data:image/svg+xml;base64,' + btoa(`
<svg width="300" height="420" xmlns="http://www.w3.org/2000/svg">
  <rect width="300" height="420" fill="#f3f4f6"/>
  <rect x="20" y="20" width="260" height="380" fill="#e5e7eb" rx="8"/>
  <circle cx="150" cy="150" r="30" fill="#d1d5db"/>
  <rect x="100" y="200" width="100" height="8" fill="#d1d5db" rx="4"/>
  <rect x="80" y="220" width="140" height="6" fill="#d1d5db" rx="3"/>
  <rect x="90" y="240" width="120" height="6" fill="#d1d5db" rx="3"/>
  <text x="150" y="320" text-anchor="middle" fill="#9ca3af" font-family="Arial" font-size="12">No Image</text>
  <text x="150" y="340" text-anchor="middle" fill="#9ca3af" font-family="Arial" font-size="12">Available</text>
</svg>
`);

const formatPrice = (cents, currency = 'EUR') => {
    if (cents === null || cents === undefined) return 'N/A';
    return (cents / 100).toLocaleString('it-IT', { style: 'currency', currency: currency });
};

// Active filters count
const activeFiltersCount = computed(() => {
    let count = 0;
    if (currentFilters.category) count++;
    if (currentFilters.expansion) count++;
    return count;
});

// Clear all filters
const clearFilters = () => {
    currentFilters.category = '';
    currentFilters.expansion = '';
};

// Watch for changes in any filter and reload page
watch(currentFilters, (newFilters) => {
    router.get(route('dashboard.blueprints.index'), {
        category_id: newFilters.category || undefined,
        expansion_id: newFilters.expansion || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
}, { deep: true });

</script>

<template>
    <Head title="Blueprints Dashboard" />

    <AuthenticatedLayout>
        <!-- Enhanced Header -->
        <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Blueprints</h1>
                        <p class="text-gray-600 dark:text-gray-400 mt-1">
                            {{ blueprints.total }} cards available • {{ blueprints.data.length }} showing
                        </p>
                    </div>
                    <div class="mt-4 sm:mt-0 flex items-center space-x-3">
                        <!-- View Mode Toggle -->
                        <div class="flex items-center bg-gray-100 dark:bg-gray-700 rounded-lg p-1">
                            <button 
                                @click="viewMode = 'grid'"
                                :class="viewMode === 'grid' ? 'bg-white dark:bg-gray-600 shadow-sm' : ''"
                                class="p-2 rounded-md transition-all duration-200"
                            >
                                <svg class="h-4 w-4 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                                </svg>
                            </button>
                            <button 
                                @click="viewMode = 'list'"
                                :class="viewMode === 'list' ? 'bg-white dark:bg-gray-600 shadow-sm' : ''"
                                class="p-2 rounded-md transition-all duration-200"
                            >
                                <svg class="h-4 w-4 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
                                </svg>
                            </button>
                        </div>

                        <!-- Filters Dropdown -->
                        <div class="relative">
                            <button 
                                @click="showFilters = !showFilters"
                                class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-red-500 transition-colors"
                            >
                                <svg class="h-4 w-4 mr-2 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.414A1 1 0 013 6.707V4z"></path>
                                </svg>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Filters</span>
                                <span v-if="activeFiltersCount > 0" class="ml-2 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white bg-red-600 rounded-full">
                                    {{ activeFiltersCount }}
                                </span>
                                <svg class="ml-2 -mr-1 h-4 w-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="showFilters ? 'M19 9l-7 7-7-7' : 'M9 5l7 7-7 7'"></path>
                                </svg>
                            </button>

                            <!-- Filters Dropdown Panel -->
                            <div v-if="showFilters" class="absolute right-0 mt-2 w-80 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 z-50">
                                <div class="p-4">
                                    <div class="flex items-center justify-between mb-4">
                                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Filters</h3>
                                        <button 
                                            v-if="activeFiltersCount > 0"
                                            @click="clearFilters"
                                            class="text-sm text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300"
                                        >
                                            Clear All
                                        </button>
                                    </div>
                                    
                                    <div class="space-y-4">
                                        <!-- Category Filter -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Category</label>
                                            <select 
                                                v-model="currentFilters.category"
                                                class="w-full px-4 py-2.5 pl-10 pr-4 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-red-500 transition-colors"
                                            >
                                                <option value="">All Categories</option>
                                                <option v-for="category in availableCategories" :key="category.id" :value="category.id">
                                                    {{ category.name }}
                                                </option>
                                            </select>
                                        </div>

                                        <!-- Expansion Filter -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Expansion</label>
                                            <select 
                                                v-model="currentFilters.expansion"
                                                class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500 focus:border-transparent"
                                            >
                                                <option value="">All Expansions</option>
                                                <option v-for="expansion in availableExpansions" :key="expansion.id" :value="expansion.id">
                                                    {{ expansion.name }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Active Filters -->
                                    <div v-if="activeFiltersCount > 0" class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Active filters:</p>
                                        <div class="flex flex-wrap gap-2">
                                            <span v-if="currentFilters.category" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200">
                                                Category: {{ availableCategories.find(c => c.id == currentFilters.category)?.name }}
                                            </span>
                                            <span v-if="currentFilters.expansion" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200">
                                                Expansion: {{ availableExpansions.find(e => e.id == currentFilters.expansion)?.name }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="min-h-screen bg-gray-50 dark:bg-gray-900 py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <!-- Cards Container -->
                <div v-if="blueprints.data.length > 0">
                    <!-- Grid View -->
                    <div v-if="viewMode === 'grid'" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4">
                        <Link 
                            v-for="blueprint in blueprints.data" 
                            :key="blueprint.id" 
                            :href="route('dashboard.blueprints.show', blueprint.id)" 
                            class="group bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 hover:shadow-lg hover:-translate-y-1 transition-all duration-300 overflow-hidden"
                        >
                            <!-- Card Image -->
                            <div class="aspect-[3/4] relative overflow-hidden bg-gray-100 dark:bg-gray-700">
                                <img 
                                    :src="blueprint.image_url ? blueprint.image_url : placeholderImage" 
                                    :alt="blueprint.name"
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                    @error="(event) => event.target.src = placeholderImage"
                                >
                                <!-- Price Badge -->
                                <div v-if="blueprint.latest_price" class="absolute top-2 right-2 bg-yellow-600 text-white px-2 py-1 rounded-md text-xs font-semibold shadow-lg">
                                    {{ formatPrice(blueprint.latest_price.price_cents, blueprint.latest_price.currency) }}
                                </div>
                            </div>
                            
                            <!-- Card Content -->
                            <div class="p-3">
                                <h3 class="font-semibold text-sm text-gray-900 dark:text-white mb-1 line-clamp-2 leading-tight" :title="blueprint.name">
                                    {{ blueprint.name }}
                                </h3>
                                <p v-if="blueprint.expansion" class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                                    {{ blueprint.expansion.name }}
                                </p>
                                
                                <!-- Price Details -->
                                <div v-if="blueprint.latest_price" class="space-y-1">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="text-gray-500 dark:text-gray-400">Condition</span>
                                        <span class="font-medium text-gray-700 dark:text-gray-300">{{ blueprint.latest_price.condition }}</span>
                                    </div>
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="text-gray-500 dark:text-gray-400">Language</span>
                                        <span class="font-medium text-gray-700 dark:text-gray-300">{{ blueprint.latest_price.language.toUpperCase() }}</span>
                                    </div>
                                    <div v-if="blueprint.latest_price.is_foil" class="flex items-center justify-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200">
                                            ✨ Foil
                                        </span>
                                    </div>
                                </div>
                                <div v-else class="text-xs text-gray-400 dark:text-gray-500 text-center py-2">
                                    No price data
                                </div>
                            </div>
                        </Link>
                    </div>

                    <!-- List View -->
                    <div v-else class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="divide-y divide-gray-200 dark:divide-gray-700">
                            <Link 
                                v-for="blueprint in blueprints.data" 
                                :key="blueprint.id" 
                                :href="route('dashboard.blueprints.show', blueprint.id)" 
                                class="flex items-center p-4 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200"
                            >
                                <!-- Image -->
                                <div class="flex-shrink-0 w-16 h-20 rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-700">
                                    <img 
                                        :src="blueprint.image_url ? blueprint.image_url : placeholderImage" 
                                        :alt="blueprint.name"
                                        class="w-full h-full object-cover"
                                        @error="(event) => event.target.src = placeholderImage"
                                    >
                                </div>
                                
                                <!-- Content -->
                                <div class="ml-4 flex-1">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ blueprint.name }}</h3>
                                            <p v-if="blueprint.expansion" class="text-sm text-gray-500 dark:text-gray-400">{{ blueprint.expansion.name }}</p>
                                            <p class="text-xs text-gray-400 dark:text-gray-500">ID: {{ blueprint.ct_id }}</p>
                                        </div>
                                        
                                        <!-- Price -->
                                        <div v-if="blueprint.latest_price" class="text-right">
                                            <p class="text-lg font-bold text-yellow-600 dark:text-yellow-400">
                                                {{ formatPrice(blueprint.latest_price.price_cents, blueprint.latest_price.currency) }}
                                            </p>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 space-y-0.5">
                                                <p>{{ blueprint.latest_price.condition }} • {{ blueprint.latest_price.language.toUpperCase() }}</p>
                                                <p v-if="blueprint.latest_price.is_foil" class="text-yellow-600 dark:text-yellow-400">✨ Foil</p>
                                            </div>
                                        </div>
                                        <div v-else class="text-sm text-gray-400 dark:text-gray-500">
                                            No price data
                                        </div>
                                    </div>
                                </div>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="text-center py-16">
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-12 shadow-sm border border-gray-200 dark:border-gray-700">
                        <svg class="h-16 w-16 text-gray-400 dark:text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">No blueprints found</h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-4">
                            Try adjusting your filters or search terms.
                        </p>
                        <button 
                            @click="clearFilters"
                            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors"
                        >
                            Clear Filters
                        </button>
                    </div>
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
                                      'z-10 bg-red-50 border-red-500 text-red-600 dark:bg-red-900 dark:border-red-700 dark:text-red-300': link.active,
                                      'bg-white border-gray-300 text-gray-500 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-700': !link.active
                                  }"
                            />
                        </template>
                    </nav>
                </div>
            </div>
        </div>

        <!-- Click outside to close filters -->
        <div v-if="showFilters" @click="showFilters = false" class="fixed inset-0 z-40"></div>
    </AuthenticatedLayout>
</template>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style> 