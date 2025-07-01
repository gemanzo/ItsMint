<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch, reactive, computed, onMounted } from 'vue';
import axios, { AxiosError } from 'axios';
import debounce from 'lodash/debounce';

interface Blueprint {
    id: number;
    name: string;
    image_url?: string;
    ct_id?: string;
    expansion?: {
        id: number;
        name: string;
        code: string;
    };
    latest_price?: {
        price_cents: number;
        currency: string;
        condition: string;
        language: string;
        is_foil: boolean;
    };
}

interface Category {
    id: number;
    name: string;
}

interface Expansion {
    id: number;
    name: string;
}

interface PaginatedBlueprints {
    data: Blueprint[];
    total: number;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

const props = defineProps<{
    blueprints: PaginatedBlueprints;
    filters: {
        category_id?: number | null;
        expansion_id?: number | null;
        language?: string | null;
    };
    availableCategories: Category[];
    availableExpansions: Expansion[];
}>();

// Reactive object for filters
const currentFilters = reactive({
    category: props.filters.category_id || '',
    expansion: props.filters.expansion_id || '',
    language: props.filters.language || '',
});

// Filter dropdown state
const showFilters = ref(false);

// Search state
const searchQuery = ref<string>('');
const isSearching = ref(false);
const searchResults = ref<Blueprint[]>([]);
const isSearchMode = ref(false);
const searchPage = ref(1);
const hasMoreSearchResults = ref(false);

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

const formatPrice = (cents: number | null | undefined, currency = 'EUR') => {
    if (cents === null || cents === undefined) return 'N/A';
    return (cents / 100).toLocaleString('it-IT', { style: 'currency', currency: currency });
};

// Active filters count
const activeFiltersCount = computed(() => {
    let count = 0;
    if (currentFilters.category) count++;
    if (currentFilters.expansion) count++;
    if (currentFilters.language) count++;
    return count;
});

// Display data - either search results or normal blueprints
const displayData = computed(() => {
    return isSearchMode.value ? searchResults.value : props.blueprints.data;
});

// Display total count
const displayTotal = computed(() => {
    if (isSearchMode.value) {
        return searchResults.value.length + (hasMoreSearchResults.value ? '+' : '');
    }
    return props.blueprints.total;
});

const clearSearchResults = () => {
    searchResults.value = [];
    isSearchMode.value = false;
    hasMoreSearchResults.value = false;
    searchPage.value = 1;
};

// Clear all filters
const clearFilters = () => {
    currentFilters.category = '';
    currentFilters.expansion = '';
    currentFilters.language = '';
    if (!isSearchMode.value) {
        // Only reload if not in search mode
        router.get(route('dashboard.blueprints.index'), {}, {
            preserveState: true,
            replace: true,
        });
    }
};

// Clear search
const clearSearch = () => {
    searchQuery.value = '';
    clearSearchResults();
};

// Search blueprints using Elasticsearch
const searchBlueprints = async (loadMore = false) => {
    if (searchQuery.value.length < 2) {
        clearSearch();
        return;
    }

    if (!loadMore) {
        searchPage.value = 1;
        searchResults.value = [];
        isSearchMode.value = true;
    }

    isSearching.value = true;

    try {
        const requestParams: { q: string; from: number; size: number; language?: string; expansion_id?: number; category_id?: number } = { 
            q: searchQuery.value,
            from: (searchPage.value - 1) * 30,
            size: 30,
        };

        if (currentFilters.language) {
            requestParams.language = currentFilters.language;
        }
        if (currentFilters.expansion) {
            requestParams.expansion_id = Number(currentFilters.expansion);
        }
        if (currentFilters.category) {
            requestParams.category_id = Number(currentFilters.category);
        }

        const response = await axios.get('/blueprints/search', {
            params: requestParams
        });
        
        if (response.data.success && response.data.data) {
            if (loadMore) {
                searchResults.value.push(...response.data.data);
            } else {
                searchResults.value = response.data.data;
            }
            hasMoreSearchResults.value = response.data.pagination.has_more;
        } else {
            searchResults.value = [];
            hasMoreSearchResults.value = false;
        }
    } catch (error) {
        console.error('💥 Error searching blueprints:', error);
        if (axios.isAxiosError(error) && error.response) {
            console.error('💥 Error response data:', error.response.data);
            console.error('💥 Error response status:', error.response.status);
        }
        searchResults.value = [];
        hasMoreSearchResults.value = false;
    } finally {
        isSearching.value = false;
    }
};

// Load more search results
const loadMoreResults = () => {
    if (hasMoreSearchResults.value && !isSearching.value) {
        searchPage.value++;
        searchBlueprints(true);
    }
};

// Debounced search
const debouncedSearch = debounce(searchBlueprints, 300);

// Handle search input
const handleSearchInput = () => {
    if (searchQuery.value.length >= 2) {
        debouncedSearch();
    } else {
        clearSearchResults();
    }
};

// Watch for changes in filters
watch(currentFilters, (newFilters: typeof currentFilters) => {
    // If in search mode, re-run the search with the new filters
    if (isSearchMode.value && searchQuery.value.length >= 2) {
        debouncedSearch();
    } else {
        // Otherwise, reload the page with new filter parameters
        router.get(route('dashboard.blueprints.index'), {
            category_id: newFilters.category || undefined,
            expansion_id: newFilters.expansion || undefined,
            language: newFilters.language || undefined,
        }, {
            preserveState: true,
            replace: true,
        });
    }
}, { deep: true });

// Alternative search input ref for direct DOM access
const searchInputRef = ref<HTMLInputElement | null>(null);

// Alternative input handler that reads directly from DOM
const handleDirectInput = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const value = target.value;
    searchQuery.value = value;
    handleSearchInput();
};

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
                            {{ displayTotal }} cards {{ isSearchMode ? 'found' : 'available' }} • {{ displayData.length }} showing
                            <span v-if="isSearchMode" class="text-indigo-600 dark:text-indigo-400 font-medium">
                                for "{{ searchQuery }}"
                            </span>
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
                                class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                            >
                                <svg class="h-4 w-4 mr-2 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.414A1 1 0 013 6.707V4z"></path>
                                </svg>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Filters</span>
                                <span v-if="activeFiltersCount > 0" class="ml-2 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white bg-indigo-600 rounded-full">
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
                                            class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300"
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
                                                class="w-full px-4 py-2.5 pl-10 pr-4 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
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
                                                class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                            >
                                                <option value="">All Expansions</option>
                                                <option v-for="expansion in availableExpansions" :key="expansion.id" :value="expansion.id">
                                                    {{ expansion.name }}
                                                </option>
                                            </select>
                                        </div>

                                        <!-- Language Filter -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Language</label>
                                            <select 
                                                v-model="currentFilters.language"
                                                class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                            >
                                                <option value="">All Languages</option>
                                                <option value="en">English (EN)</option>
                                                <option value="jp">Japanese (JP)</option>
                                                <option value="zh-cn">Chinese (ZH-CN)</option>
                                                <option value="fr">French (FR)</option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Active Filters -->
                                    <div v-if="activeFiltersCount > 0" class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Active filters:</p>
                                        <div class="flex flex-wrap gap-2">
                                            <span v-if="currentFilters.category" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200">
                                                Category: {{ availableCategories.find(c => c.id == currentFilters.category)?.name }}
                                            </span>
                                            <span v-if="currentFilters.expansion" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                                                Expansion: {{ availableExpansions.find(e => e.id == currentFilters.expansion)?.name }}
                                            </span>
                                            <span v-if="currentFilters.language" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                                                Language: {{ currentFilters.language.toUpperCase() }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Search Bar -->
                <div class="mt-6">
                    <div class="max-w-2xl">
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </div>
                            <input 
                                type="text" 
                                :value="searchQuery"
                                @input="handleDirectInput"
                                @keyup="(e) => console.log('Keyup:', (e.target as HTMLInputElement)?.value)"
                                @focus="() => console.log('Search input focused')"
                                placeholder="Search cards by name or expansion... e.g., 'Luffy OP01' or 'Roronoa Zoro'"
                                class="block w-full pl-12 pr-12 py-4 text-lg border border-gray-300 dark:border-gray-600 rounded-xl shadow-sm placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white dark:bg-gray-700 transition-all duration-200"
                                style="color: #1f2937 !important; background-color: white !important;"
                                ref="searchInputRef"
                            />
                            <!-- Clear search button -->
                            <div class="absolute inset-y-0 right-0 pr-4 flex items-center">
                                <button 
                                    v-if="searchQuery.length > 0"
                                    @click="clearSearch"
                                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                                <div v-else-if="isSearching" class="animate-spin h-5 w-5 text-gray-400">
                                    <svg fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Search hint -->
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            💡 Try searching with card name + expansion: "Monkey D. Luffy OP01" or just "Luffy"
                        </p>
                        
                        <!-- Search mode indicator -->
                        <div v-if="isSearchMode" class="mt-2 flex items-center justify-between">
                            <div class="flex items-center text-sm text-indigo-600 dark:text-indigo-400">
                                <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                                Search mode active - showing Elasticsearch results
                            </div>
                            <button 
                                @click="clearSearch"
                                class="text-sm text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200 underline"
                            >
                                Show all cards
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="min-h-screen bg-gray-50 dark:bg-gray-900 py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <!-- Loading state -->
                <div v-if="isSearching && searchResults.length === 0" class="text-center py-16">
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-12 shadow-sm border border-gray-200 dark:border-gray-700">
                        <div class="animate-spin h-8 w-8 text-indigo-600 mx-auto mb-4">
                            <svg fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Searching...</h3>
                        <p class="text-gray-600 dark:text-gray-400">Finding cards matching "{{ searchQuery }}"</p>
                    </div>
                </div>
                
                <!-- Cards Container -->
                <div v-else-if="displayData.length > 0">
                    <!-- Grid View -->
                    <div v-if="viewMode === 'grid'" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4">
                        <Link 
                            v-for="blueprint in displayData" 
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
                                    @error="(event: Event) => (event.target as HTMLImageElement).src = placeholderImage"
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
                                v-for="blueprint in displayData" 
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
                                        @error="(event: Event) => (event.target as HTMLImageElement).src = placeholderImage"
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
                    
                    <!-- Load More Button (for search results) -->
                    <div v-if="isSearchMode && hasMoreSearchResults" class="mt-8 text-center">
                        <button 
                            @click="loadMoreResults"
                            :disabled="isSearching"
                            class="px-6 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            {{ isSearching ? 'Loading...' : 'Load More Results' }}
                        </button>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="text-center py-16">
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-12 shadow-sm border border-gray-200 dark:border-gray-700">
                        <svg class="h-16 w-16 text-gray-400 dark:text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
                            {{ isSearchMode ? 'No cards found' : 'No blueprints found' }}
                        </h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-4">
                            {{ isSearchMode 
                                ? `No cards match your search for "${searchQuery}". Try a different search term.`
                                : 'Try adjusting your filters or search terms.'
                            }}
                        </p>
                        <div class="space-x-3">
                            <button 
                                v-if="isSearchMode"
                                @click="clearSearch"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors"
                            >
                                Clear Search
                            </button>
                            <button 
                                v-if="activeFiltersCount > 0"
                                @click="clearFilters"
                                class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors"
                            >
                                Clear Filters
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Pagination (only for normal view, not search) -->
                <div v-if="!isSearchMode && blueprints.links.length > 3" class="mt-8 flex justify-center">
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