<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import PriceHistoryChart from '@/Components/PriceHistoryChart.vue';
import axios from 'axios';

const props = defineProps({
    blueprint: Object,
});

// Reactive variables
const showAddToPortfolioModal = ref(false);
const imageLoaded = ref(false);
const imageError = ref(false);
const priceHistory = ref(null);
const isLoadingChart = ref(true);

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

const formatDateShort = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('it-IT', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
};

// Computed properties
const conditionColor = computed(() => {
    if (!props.blueprint.latest_price) return 'gray';
    const condition = props.blueprint.latest_price.condition.toLowerCase();
    
    if (condition.includes('mint') || condition.includes('near mint')) return 'green';
    if (condition.includes('excellent') || condition.includes('lightly played')) return 'blue';
    if (condition.includes('good') || condition.includes('moderately played')) return 'yellow';
    if (condition.includes('played') || condition.includes('damaged')) return 'red';
    return 'gray';
});

const priceStatus = computed(() => {
    if (!props.blueprint.latest_price) return null;
    const fetched = new Date(props.blueprint.latest_price.fetched_at);
    const now = new Date();
    const hoursDiff = (now - fetched) / (1000 * 60 * 60);
    
    if (hoursDiff < 1) return { text: 'Just updated', color: 'green' };
    if (hoursDiff < 24) return { text: `${Math.floor(hoursDiff)}h ago`, color: 'blue' };
    if (hoursDiff < 168) return { text: `${Math.floor(hoursDiff / 24)}d ago`, color: 'yellow' };
    return { text: 'Outdated', color: 'red' };
});

// Methods
const addToPortfolio = () => {
    showAddToPortfolioModal.value = true;
    // TODO: Implement add to portfolio functionality
    console.log('Add to portfolio:', props.blueprint.id);
};

const copyToClipboard = (text) => {
    navigator.clipboard.writeText(text);
    // TODO: Show toast notification
    console.log('Copied to clipboard:', text);
};

const goBack = () => {
    router.visit(route('dashboard.blueprints.index'));
};

const fetchPriceHistory = async () => {
    try {
        const response = await axios.get(route('api.blueprints.price-history', { blueprint: props.blueprint.id }));
        const history = response.data;

        if (history.length === 0) {
            priceHistory.value = null;
            return;
        }

        const labels = history.map(p => formatDateShort(p.fetched_at));
        const data = history.map(p => p.price_cents / 100);

        priceHistory.value = {
            labels,
            datasets: [
                {
                    label: `Price (${history[0]?.currency || 'EUR'})`,
                    backgroundColor: 'rgba(239, 68, 68, 0.2)',
                    borderColor: 'rgba(239, 68, 68, 1)',
                    data,
                    tension: 0.1,
                },
            ],
        };
    } catch (error) {
        console.error('Failed to fetch price history:', error);
        priceHistory.value = null;
    } finally {
        isLoadingChart.value = false;
    }
};

onMounted(() => {
    fetchPriceHistory();
});
</script>

<template>
    <Head :title="`${blueprint.name} - Blueprint Details`" />

    <AuthenticatedLayout>
        <!-- Hero Section with Image and Main Info -->
        <div class="relative bg-gradient-to-r from-red-600 via-orange-600 to-yellow-600 text-white overflow-hidden">
            <!-- Luffy Background Image -->
            <div class="absolute inset-0 opacity-40">
                <img 
                    src="/images/one-piece-monkey-d-luffy-poster-skvkdnzrso4q5b0u.webp" 
                    alt="Monkey D. Luffy" 
                    class="w-full h-full object-cover object-[center_20%]"
                    @error="$event.target.style.display = 'none'"
                />
            </div>
            <!-- Gradient Overlay for text readability -->
            <div class="absolute inset-0 bg-gradient-to-r from-red-600/60 via-orange-600/60 to-yellow-600/60"></div>
            
            <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
                <!-- Breadcrumbs -->
                <nav class="flex items-center space-x-2 text-red-100 mb-3">
                    <Link :href="route('dashboard')" class="hover:text-white transition-colors text-xs">Dashboard</Link>
                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                    <Link :href="route('dashboard.blueprints.index')" class="hover:text-white transition-colors text-xs">Blueprints</Link>
                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                    <span class="text-white font-medium text-xs">{{ blueprint.name }}</span>
                </nav>

                <!-- Main Content -->
                <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 items-center">
                    <!-- Card Image -->
                    <div class="lg:col-span-1">
                        <div class="relative group max-w-[120px] mx-auto lg:mx-0">
                            <div class="bg-white p-2 rounded-lg shadow-lg transform group-hover:scale-105 transition-transform duration-300 border-2 border-white/20">
                                <img 
                                    :src="blueprint.image_url ? blueprint.image_url : placeholderImage" 
                                    :alt="blueprint.name"
                                    class="w-full h-auto object-contain rounded"
                                    @load="imageLoaded = true"
                                    @error="imageError = true; $event.target.src = placeholderImage"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Main Info -->
                    <div class="lg:col-span-3">
                        <div class="space-y-3">
                            <!-- Title and Price -->
                            <div>
                                <h1 class="text-xl lg:text-2xl font-bold mb-2 text-white drop-shadow-lg">{{ blueprint.name }}</h1>
                                <div class="flex flex-wrap items-center gap-3">
                                    <div v-if="blueprint.latest_price" class="bg-white/90 backdrop-blur-sm rounded-lg px-3 py-1 shadow-lg">
                                        <p class="text-lg lg:text-xl font-bold text-red-700">
                                            {{ formatPrice(blueprint.latest_price.price_cents, blueprint.latest_price.currency) }}
                                        </p>
                                        <p class="text-xs text-red-600">Market Price</p>
                                    </div>
                                    <div class="flex flex-wrap gap-1">
                                        <span v-if="blueprint.expansion" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-white/90 text-red-800 shadow-sm">
                                            {{ blueprint.expansion.name }}
                                        </span>
                                        <span v-if="blueprint.category" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-white/90 text-orange-800 shadow-sm">
                                            {{ blueprint.category.name }}
                                        </span>
                                        <span v-if="blueprint.latest_price && blueprint.latest_price.is_foil" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-white/90 text-yellow-800 shadow-sm">
                                            ✨ Foil
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Quick Actions -->
                            <div class="flex flex-wrap gap-2">
                                <button 
                                    @click="addToPortfolio"
                                    class="inline-flex items-center px-3 py-1.5 bg-white/95 backdrop-blur-sm text-red-600 font-semibold rounded-md shadow-lg hover:bg-white transition-colors duration-200 text-xs"
                                >
                                    <svg class="h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                    Add to Portfolio
                                </button>
                                <button 
                                    @click="copyToClipboard(blueprint.ct_id)"
                                    class="inline-flex items-center px-3 py-1.5 bg-white/20 backdrop-blur-sm text-white font-medium rounded-md hover:bg-white/30 transition-colors duration-200 text-xs border border-white/30"
                                >
                                    <svg class="h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                    </svg>
                                    Copy ID
                                </button>
                                <Link 
                                    :href="route('dashboard.blueprints.index')"
                                    class="inline-flex items-center px-3 py-1.5 bg-white/20 backdrop-blur-sm text-white font-medium rounded-md hover:bg-white/30 transition-colors duration-200 text-xs border border-white/30"
                                >
                                    <svg class="h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                                    </svg>
                                    Back
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Information Section -->
        <div class="min-h-screen bg-gray-50 dark:bg-gray-900 py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    
                    <!-- Left Column - Main Details -->
                    <div class="lg:col-span-2 space-y-6">
                        
                        <!-- Card Information -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700">
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                                <svg class="h-5 w-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                Card Information
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Card Name</p>
                                    <p class="text-lg font-semibold text-gray-900 dark:text-white mt-1">{{ blueprint.name }}</p>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Version</p>
                                    <p class="text-lg text-gray-900 dark:text-white mt-1">{{ blueprint.version || 'N/A' }}</p>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Expansion</p>
                                    <p class="text-lg text-gray-900 dark:text-white mt-1">{{ blueprint.expansion?.name || 'N/A' }}</p>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Category</p>
                                    <p class="text-lg text-gray-900 dark:text-white mt-1">{{ blueprint.category?.name || 'N/A' }}</p>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">CardTrader ID</p>
                                    <div class="flex items-center mt-1">
                                        <p class="text-lg font-mono text-gray-900 dark:text-white">{{ blueprint.ct_id }}</p>
                                        <button 
                                            @click="copyToClipboard(blueprint.ct_id)"
                                            class="ml-2 p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                                            title="Copy to clipboard"
                                        >
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Added to Database</p>
                                    <p class="text-lg text-gray-900 dark:text-white mt-1">{{ formatDateShort(blueprint.created_at) }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Price History (Placeholder) -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700">
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                                <svg class="h-5 w-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2-2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                                Price History
                            </h3>
                            <div class="h-40 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                                <div class="text-center">
                                    <svg class="h-8 w-8 text-gray-400 dark:text-gray-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2-2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                    </svg>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Price history chart coming soon</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column - Price & Market Info -->
                    <div class="space-y-6">
                        
                        <!-- Current Price Card -->
                        <div v-if="blueprint.latest_price" class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Current Price</h3>
                                <span v-if="priceStatus" 
                                      :class="`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-${priceStatus.color}-100 text-${priceStatus.color}-800 dark:bg-${priceStatus.color}-900 dark:text-${priceStatus.color}-200`">
                                    {{ priceStatus.text }}
                                </span>
                            </div>
                            
                            <div class="space-y-4">
                                <div class="text-center p-4 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-800">
                                    <p class="text-3xl font-bold text-green-600 dark:text-green-400">
                                        {{ formatPrice(blueprint.latest_price.price_cents, blueprint.latest_price.currency) }}
                                    </p>
                                    <p class="text-sm text-green-600 dark:text-green-400 mt-1">Market Price</p>
                                </div>

                                <div class="space-y-3">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Condition</span>
                                        <span :class="`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-${conditionColor}-100 text-${conditionColor}-800 dark:bg-${conditionColor}-900 dark:text-${conditionColor}-200`">
                                            {{ blueprint.latest_price.condition }}
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Language</span>
                                        <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ blueprint.latest_price.language.toUpperCase() }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Foil</span>
                                        <span class="text-sm font-semibold text-gray-900 dark:text-white">
                                            {{ blueprint.latest_price.is_foil ? '✨ Yes' : 'No' }}
                                        </span>
                                    </div>
                                </div>

                                <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                                    <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                        <span>Source: {{ blueprint.latest_price.source }}</span>
                                        <span>{{ formatDate(blueprint.latest_price.fetched_at) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- No Price Data -->
                        <div v-else class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700">
                            <div class="text-center py-8">
                                <svg class="h-12 w-12 text-gray-400 dark:text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                </svg>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">No Price Data</h3>
                                <p class="text-gray-600 dark:text-gray-400 text-sm">Price information is not available for this card yet.</p>
                            </div>
                        </div>

                        <!-- Market Stats (Placeholder) -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Market Statistics</h3>
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500 dark:text-gray-400">Market Cap</span>
                                    <span class="text-sm font-semibold text-gray-900 dark:text-white">Coming Soon</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500 dark:text-gray-400">Avg. Price (30d)</span>
                                    <span class="text-sm font-semibold text-gray-900 dark:text-white">Coming Soon</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500 dark:text-gray-400">Price Change</span>
                                    <span class="text-sm font-semibold text-gray-900 dark:text-white">Coming Soon</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500 dark:text-gray-400">Popularity Rank</span>
                                    <span class="text-sm font-semibold text-gray-900 dark:text-white">Coming Soon</span>
                                </div>
                            </div>
                        </div>

                        <!-- Technical Details -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Technical Details</h3>
                            <div class="space-y-3 text-sm">
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">Database ID</span>
                                    <span class="font-mono text-gray-900 dark:text-white">{{ blueprint.id }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">Last Updated</span>
                                    <span class="text-gray-900 dark:text-white">{{ formatDateShort(blueprint.updated_at) }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">Data Source</span>
                                    <span class="text-gray-900 dark:text-white">CardTrader</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add to Portfolio Modal (Placeholder) -->
        <div v-if="showAddToPortfolioModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex min-h-screen items-center justify-center p-4">
                <div class="fixed inset-0 bg-black bg-opacity-25" @click="showAddToPortfolioModal = false"></div>
                <div class="relative bg-white dark:bg-gray-800 rounded-xl p-6 max-w-md w-full">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Add to Portfolio</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">Portfolio selection functionality coming soon!</p>
                    <button 
                        @click="showAddToPortfolioModal = false"
                        class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                    >
                        Got it!
                    </button>
                </div>
            </div>
        </div>

        <!-- Price History Chart -->
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Price History</h3>
                    <div v-if="isLoadingChart" class="text-center">
                        <p>Loading chart...</p>
                    </div>
                    <div v-else-if="priceHistory">
                        <PriceHistoryChart :chartData="priceHistory" style="height: 300px" />
                    </div>
                    <div v-else class="text-center text-gray-500">
                        <p>No price history available for this blueprint.</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Custom animations and transitions */
.group:hover .group-hover\:scale-105 {
    transform: scale(1.05);
}
</style> 