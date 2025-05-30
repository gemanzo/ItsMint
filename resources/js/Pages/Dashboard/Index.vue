<template>
    <AuthenticatedLayout>
        <Head title="Dashboard" />

        <!-- Enhanced Header -->
        <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Dashboard</h1>
                        <p class="text-gray-600 dark:text-gray-400 mt-1">Welcome back! Here's your portfolio overview.</p>
                    </div>
                    <div class="flex space-x-3">
                        <button 
                            @click="syncPrices"
                            :disabled="isSyncing"
                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white rounded-lg transition-colors flex items-center space-x-2"
                        >
                            <svg v-if="isSyncing" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            <span>{{ isSyncing ? 'Syncing...' : 'Sync Prices' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="min-h-screen bg-gray-50 dark:bg-gray-900 py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <!-- Enhanced Metrics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <!-- Total Estimated Value -->
                    <div class="bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-800/20 rounded-xl p-6 border border-green-200 dark:border-green-800 shadow-sm hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <p class="text-green-600 dark:text-green-400 text-sm font-medium uppercase tracking-wide">Total Estimated Value</p>
                                <p class="text-3xl font-bold text-green-900 dark:text-green-100 mt-2">{{ formatCurrency(totalEstimatedPortfolioValue) }}</p>
                                <div class="flex items-center mt-3">
                                    <svg class="h-4 w-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                    </svg>
                                    <span class="text-green-600 dark:text-green-400 text-sm ml-1 font-medium">Portfolio Value</span>
                                </div>
                            </div>
                            <div class="h-14 w-14 bg-green-500 rounded-xl flex items-center justify-center shadow-lg">
                                <svg class="h-7 w-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Total Purchase Cost -->
                    <div class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 rounded-xl p-6 border border-blue-200 dark:border-blue-800 shadow-sm hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <p class="text-blue-600 dark:text-blue-400 text-sm font-medium uppercase tracking-wide">Total Purchase Cost</p>
                                <p class="text-3xl font-bold text-blue-900 dark:text-blue-100 mt-2">{{ formatCurrency(totalPurchasePortfolioCost) }}</p>
                                <div class="flex items-center mt-3">
                                    <svg class="h-4 w-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                    <span class="text-blue-600 dark:text-blue-400 text-sm ml-1 font-medium">Initial Investment</span>
                                </div>
                            </div>
                            <div class="h-14 w-14 bg-blue-500 rounded-xl flex items-center justify-center shadow-lg">
                                <svg class="h-7 w-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Total Profit/Loss -->
                    <div class="rounded-xl p-6 shadow-sm hover:shadow-lg transition-all duration-300 hover:-translate-y-1" :class="profitLossCardClasses">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <p class="text-sm font-medium uppercase tracking-wide" :class="profitLossTextClasses">Total Profit / Loss</p>
                                <p class="text-3xl font-bold mt-2" :class="profitLossValueClasses">{{ formatCurrency(totalProfitLoss) }}</p>
                                <div class="flex items-center mt-3">
                                    <svg v-if="totalProfitLoss >= 0" class="h-4 w-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                    </svg>
                                    <svg v-else class="h-4 w-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path>
                                    </svg>
                                    <span class="text-sm ml-1 font-medium" :class="profitLossTextClasses">
                                        {{ totalProfitLoss >= 0 ? 'Profit' : 'Loss' }}
                                    </span>
                                </div>
                            </div>
                            <div class="h-14 w-14 rounded-xl flex items-center justify-center shadow-lg" :class="profitLossIconBgClasses">
                                <svg v-if="totalProfitLoss >= 0" class="h-7 w-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"></path>
                                </svg>
                                <svg v-else class="h-7 w-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions Hub -->
                <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm mb-8 border border-gray-200 dark:border-gray-700">
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-6">⚡ Quick Actions</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <button 
                            @click="showCreatePortfolioModal = true"
                            class="group flex flex-col items-center p-6 rounded-xl bg-purple-50 dark:bg-purple-900/20 hover:bg-purple-100 dark:hover:bg-purple-900/30 border border-purple-200 dark:border-purple-800 transition-all duration-300 hover:scale-105 hover:shadow-md"
                        >
                            <div class="h-12 w-12 bg-purple-500 rounded-lg flex items-center justify-center mb-3 group-hover:bg-purple-600 transition-colors">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                            </div>
                            <span class="text-sm font-semibold text-purple-700 dark:text-purple-300 group-hover:text-purple-800 dark:group-hover:text-purple-200">New Portfolio</span>
                            <span class="text-xs text-purple-500 dark:text-purple-400 mt-1">Create collection</span>
                        </button>
                        
                        <Link :href="route('portfolios.index')" 
                            class="group flex flex-col items-center p-6 rounded-xl bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/30 border border-blue-200 dark:border-blue-800 transition-all duration-300 hover:scale-105 hover:shadow-md"
                        >
                            <div class="h-12 w-12 bg-blue-500 rounded-lg flex items-center justify-center mb-3 group-hover:bg-blue-600 transition-colors">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                </svg>
                            </div>
                            <span class="text-sm font-semibold text-blue-700 dark:text-blue-300 group-hover:text-blue-800 dark:group-hover:text-blue-200">View Portfolios</span>
                            <span class="text-xs text-blue-500 dark:text-blue-400 mt-1">Manage collections</span>
                        </Link>
                        
                        <Link :href="route('dashboard.blueprints.index')" 
                            class="group flex flex-col items-center p-6 rounded-xl bg-orange-50 dark:bg-orange-900/20 hover:bg-orange-100 dark:hover:bg-orange-900/30 border border-orange-200 dark:border-orange-800 transition-all duration-300 hover:scale-105 hover:shadow-md"
                        >
                            <div class="h-12 w-12 bg-orange-500 rounded-lg flex items-center justify-center mb-3 group-hover:bg-orange-600 transition-colors">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </div>
                            <span class="text-sm font-semibold text-orange-700 dark:text-orange-300 group-hover:text-orange-800 dark:group-hover:text-orange-200">Search Cards</span>
                            <span class="text-xs text-orange-500 dark:text-orange-400 mt-1">Browse catalog</span>
                        </Link>
                        
                        <button 
                            @click="showPriceAlertsModal = true"
                            class="group flex flex-col items-center p-6 rounded-xl bg-green-50 dark:bg-green-900/20 hover:bg-green-100 dark:hover:bg-green-900/30 border border-green-200 dark:border-green-800 transition-all duration-300 hover:scale-105 hover:shadow-md"
                        >
                            <div class="h-12 w-12 bg-green-500 rounded-lg flex items-center justify-center mb-3 group-hover:bg-green-600 transition-colors">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5 5-5-5h5zm-5-5l5-5 5 5h-5v-5z"></path>
                                </svg>
                            </div>
                            <span class="text-sm font-semibold text-green-700 dark:text-green-300 group-hover:text-green-800 dark:group-hover:text-green-200">Price Alerts</span>
                            <span class="text-xs text-green-500 dark:text-green-400 mt-1">Track changes</span>
                        </button>
                    </div>
                </div>

                <!-- Two Column Layout -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Left Column - Recent Activity -->
                    <div class="lg:col-span-2">
                        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between mb-6">
                                <h3 class="text-xl font-semibold text-gray-900 dark:text-white">📈 Recent Activity</h3>
                                <Link :href="route('portfolios.index')" class="text-blue-600 dark:text-blue-400 text-sm hover:text-blue-700 dark:hover:text-blue-300 font-medium">
                                    View All →
                                </Link>
                            </div>
                            
                            <div v-if="recentActivities.length > 0" class="space-y-4">
                                <div v-for="activity in recentActivities" :key="activity.id" class="flex items-center space-x-4 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                    <div class="h-10 w-10 rounded-full flex items-center justify-center" :class="activity.iconBg">
                                        <svg class="h-5 w-5" :class="activity.iconColor" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="activity.iconPath"></path>
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ activity.title }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ activity.time }}</p>
                                    </div>
                                    <div class="text-right">
                                        <span v-if="activity.value" class="text-sm font-semibold" :class="activity.valueColor">{{ activity.value }}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div v-else class="text-center py-12">
                                <svg class="h-12 w-12 text-gray-400 dark:text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No Recent Activity</h4>
                                <p class="text-gray-500 dark:text-gray-400 mb-4">Start by creating a portfolio or adding cards to see activity here.</p>
                                <button 
                                    @click="showCreatePortfolioModal = true"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                                >
                                    Create Portfolio
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column - Portfolio Summary -->
                    <div class="space-y-6">
                        <!-- Portfolio Count -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="text-lg font-semibold text-gray-900 dark:text-white">📂 Portfolios</h4>
                                <span class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ portfolioStore.portfolios.length }}</span>
                            </div>
                            <div class="space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-400">Total Cards</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ totalCards }}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-400">Avg. Portfolio Value</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ formatCurrency(averagePortfolioValue) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Chart Placeholder -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700">
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">📊 Performance</h4>
                            <div class="h-40 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                                <div class="text-center">
                                    <svg class="h-8 w-8 text-gray-400 dark:text-gray-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                    </svg>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Chart Coming Soon</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Getting Started Guide (if no portfolios) -->
                <div v-if="portfolioStore.portfolios.length === 0 && !portfolioStore.isLoading" class="bg-white dark:bg-gray-800 rounded-xl p-8 shadow-sm border border-gray-200 dark:border-gray-700 mt-8">
                    <div class="text-center">
                        <svg class="h-16 w-16 text-blue-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Welcome to ItsMint! 🎉</h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-6 max-w-2xl mx-auto">
                            Start building your One Piece TCG collection by creating your first portfolio. 
                            Track card values, monitor performance, and organize your collection like a pro.
                        </p>
                        <div class="flex flex-col sm:flex-row gap-4 justify-center">
                            <button 
                                @click="showCreatePortfolioModal = true"
                                class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg shadow-lg hover:shadow-xl transition-all duration-300"
                            >
                                🎯 Create Your First Portfolio
                            </button>
                            <Link :href="route('dashboard.blueprints.index')" 
                                class="px-6 py-3 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 font-semibold rounded-lg transition-colors"
                            >
                                🔍 Browse Card Catalog
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modals -->
        <CreatePortfolioModal v-if="showCreatePortfolioModal" @close="showCreatePortfolioModal = false" @portfolio-created="handlePortfolioCreated" />
        
        <!-- Price Alerts Modal (Placeholder for now) -->
        <div v-if="showPriceAlertsModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex min-h-screen items-center justify-center p-4">
                <div class="fixed inset-0 bg-black bg-opacity-25" @click="showPriceAlertsModal = false"></div>
                <div class="relative bg-white dark:bg-gray-800 rounded-xl p-6 max-w-md w-full">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">🔔 Price Alerts</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">Price alert functionality coming soon! We'll notify you when your cards' values change significantly.</p>
                    <button 
                        @click="showPriceAlertsModal = false"
                        class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                    >
                        Got it!
                    </button>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import { usePortfolioStore, Portfolio, UserCard } from '../../stores/portfolioStore'; 
import CreatePortfolioModal from '../../Components/CreatePortfolioModal.vue';

const page = usePage();
const portfolioStore = usePortfolioStore();

// Reactive variables
const showCreatePortfolioModal = ref(false);
const showPriceAlertsModal = ref(false);
const isSyncing = ref(false);

onMounted(async () => {
    // Fetch all portfolios to calculate totals. 
    // This might already be called if navigating from login, but good to ensure.
    if (portfolioStore.portfolios.length === 0) {
        await portfolioStore.fetchPortfolios();
    }
    
    // Debug: log the portfolios data
    console.log('Dashboard portfolios data:', portfolioStore.portfolios);
    if (portfolioStore.portfolios.length > 0 && portfolioStore.portfolios[0].user_cards) {
        console.log('First portfolio user_cards:', portfolioStore.portfolios[0].user_cards);
        if (portfolioStore.portfolios[0].user_cards.length > 0) {
            console.log('First card estimated_value_cents:', portfolioStore.portfolios[0].user_cards[0].estimated_value_cents);
        }
    }
});

// Methods
const formatCurrency = (value: number | null | undefined, currency = 'EUR') => {
    if (value === null || typeof value === 'undefined') return 'N/A';
    return new Intl.NumberFormat('it-IT', { style: 'currency', currency: currency }).format(value);
};

const syncPrices = async () => {
    isSyncing.value = true;
    try {
        // Simulate price sync - in real implementation this would call an API
        await new Promise(resolve => setTimeout(resolve, 2000));
        // TODO: Implement actual price sync logic
        console.log('Price sync completed');
    } catch (error) {
        console.error('Price sync failed:', error);
    } finally {
        isSyncing.value = false;
    }
};

const handlePortfolioCreated = () => {
    console.log('Portfolio created event received on dashboard');
    // Store updates itself, dashboard computed properties will react.
    // Optionally, redirect to the new portfolio or the portfolios index:
    // router.visit(route('portfolios.index'));
};

// Computed properties for financial metrics
const totalEstimatedPortfolioValue = computed(() => {
    if (!Array.isArray(portfolioStore.portfolios)) {
        return 0;
    }
    return portfolioStore.portfolios.reduce((total, portfolio) => {
        const portfolioValue = portfolio.user_cards?.reduce((sum, card) => {
            const value = card.estimated_value_cents ? card.estimated_value_cents / 100 : 0;
            return sum + (value * (card.pivot?.quantity || 1));
        }, 0) || 0;
        return total + portfolioValue;
    }, 0);
});

const totalPurchasePortfolioCost = computed(() => {
    if (!Array.isArray(portfolioStore.portfolios)) {
        return 0;
    }
    return portfolioStore.portfolios.reduce((total, portfolio) => {
        const portfolioCost = portfolio.user_cards?.reduce((sum, card) => {
            const cost = card.purchase_price_cents ? card.purchase_price_cents / 100 : 0;
            return sum + (cost * (card.pivot?.quantity || 1));
        }, 0) || 0;
        return total + portfolioCost;
    }, 0);
});

const totalProfitLoss = computed(() => {
    return totalEstimatedPortfolioValue.value - totalPurchasePortfolioCost.value;
});

// Computed properties for styling profit/loss cards
const profitLossCardClasses = computed(() => {
    return totalProfitLoss.value >= 0 
        ? 'bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-800/20 border border-green-200 dark:border-green-800'
        : 'bg-gradient-to-br from-red-50 to-red-100 dark:from-red-900/20 dark:to-red-800/20 border border-red-200 dark:border-red-800';
});

const profitLossTextClasses = computed(() => {
    return totalProfitLoss.value >= 0 
        ? 'text-green-600 dark:text-green-400'
        : 'text-red-600 dark:text-red-400';
});

const profitLossValueClasses = computed(() => {
    return totalProfitLoss.value >= 0 
        ? 'text-green-900 dark:text-green-100'
        : 'text-red-900 dark:text-red-100';
});

const profitLossIconBgClasses = computed(() => {
    return totalProfitLoss.value >= 0 
        ? 'bg-green-500'
        : 'bg-red-500';
});

// Computed properties for portfolio statistics
const totalCards = computed(() => {
    if (!Array.isArray(portfolioStore.portfolios)) {
        return 0;
    }
    return portfolioStore.portfolios.reduce((total, portfolio) => {
        const portfolioCards = portfolio.user_cards?.reduce((sum, card) => {
            return sum + (card.pivot?.quantity || 1);
        }, 0) || 0;
        return total + portfolioCards;
    }, 0);
});

const averagePortfolioValue = computed(() => {
    const portfolioCount = portfolioStore.portfolios.length;
    return portfolioCount > 0 ? totalEstimatedPortfolioValue.value / portfolioCount : 0;
});

// Mock recent activities for now
const recentActivities = computed(() => {
    // In a real implementation, this would come from the API/store
    // For now, return mock data to show the UI structure
    return [
        {
            id: 1,
            title: 'Added Monkey.D.Luffy to Main Collection',
            time: '2 hours ago',
            value: '+15.50 €',
            valueColor: 'text-green-600',
            iconBg: 'bg-green-100 dark:bg-green-900/30',
            iconColor: 'text-green-600',
            iconPath: 'M12 6v6m0 0v6m0-6h6m-6 0H6'
        },
        {
            id: 2,
            title: 'Portfolio "Championship Deck" created',
            time: '1 day ago',
            value: null,
            valueColor: '',
            iconBg: 'bg-blue-100 dark:bg-blue-900/30',
            iconColor: 'text-blue-600',
            iconPath: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10'
        },
        {
            id: 3,
            title: 'Trafalgar Law price updated',
            time: '2 days ago',
            value: '+5.20 €',
            valueColor: 'text-green-600',
            iconBg: 'bg-orange-100 dark:bg-orange-900/30',
            iconColor: 'text-orange-600',
            iconPath: 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'
        }
    ].slice(0, portfolioStore.portfolios.length > 0 ? 3 : 0); // Show activities only if user has portfolios
});

</script>

<style scoped>
/* Add any page-specific styles here */
</style> 