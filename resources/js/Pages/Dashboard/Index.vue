<template>
    <AuthenticatedLayout>
        <Head title="Dashboard" />

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <!-- Main Dashboard Content -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                            <!-- Portfolio Value -->
                            <div class="bg-gray-100 dark:bg-gray-700 p-6 rounded-lg shadow">
                                <h3 class="text-lg font-semibold mb-2">Total Estimated Value</h3>
                                <p class="text-3xl font-bold">{{ formatCurrency(totalEstimatedPortfolioValue) }}</p>
                            </div>

                            <!-- Total Purchase Cost -->
                            <div class="bg-gray-100 dark:bg-gray-700 p-6 rounded-lg shadow">
                                <h3 class="text-lg font-semibold mb-2">Total Purchase Cost</h3>
                                <p class="text-3xl font-bold">{{ formatCurrency(totalPurchasePortfolioCost) }}</p>
                            </div>

                            <!-- Total Profit/Loss -->
                            <div class="bg-gray-100 dark:bg-gray-700 p-6 rounded-lg shadow" :class="{
                                'text-green-600 dark:text-green-400': totalProfitLoss >= 0,
                                'text-red-600 dark:text-red-400': totalProfitLoss < 0
                            }">
                                <h3 class="text-lg font-semibold mb-2">Total Profit / Loss</h3>
                                <p class="text-3xl font-bold">{{ formatCurrency(totalProfitLoss) }}</p>
                            </div>
                        </div>
                        
                        <div class="flex justify-start mb-6">
                             <button 
                                @click="showCreatePortfolioModal = true"
                                class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg shadow">
                                + Create New Portfolio
                            </button>
                        </div>

                        <!-- Chart Area (Placeholder) -->
                        <div class="bg-gray-100 dark:bg-gray-700 p-6 rounded-lg shadow mb-6">
                            <h3 class="text-lg font-semibold mb-2">Portfolio Performance Overview</h3>
                            <div class="h-64">
                                <p>Chart will be here (e.g., total value over time)</p>
                            </div>
                        </div>

                        <!-- Removed detailed portfolio list from here -->
                        <div v-if="portfolioStore.isLoading">
                            <p>Loading portfolio data...</p>
                        </div>
                        <div v-else-if="!portfolioStore.isLoading && portfolioStore.portfolios.length === 0">
                            <p>You don't have any portfolios yet. <Link :href="route('portfolios.index')" class="text-indigo-600 hover:underline">Go to Portfolios to create one!</Link></p>
                        </div>
                         <div v-else>
                            <p>View your detailed portfolios <Link :href="route('portfolios.index')" class="text-indigo-600 hover:underline">in the Portfolios section</Link>.</p>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <CreatePortfolioModal v-if="showCreatePortfolioModal" @close="showCreatePortfolioModal = false" @portfolio-created="handlePortfolioCreated" />
        <!-- AddCardModal is removed from here as cards are added via Portfolios/Show or Portfolios/Index contextually -->

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

const showCreatePortfolioModal = ref(false);

onMounted(async () => {
    // Fetch all portfolios to calculate totals. 
    // This might already be called if navigating from login, but good to ensure.
    if (portfolioStore.portfolios.length === 0) {
        await portfolioStore.fetchPortfolios();
    }
});

const formatCurrency = (value: number | null | undefined, currency = 'EUR') => {
    if (value === null || typeof value === 'undefined') return 'N/A';
    return new Intl.NumberFormat('it-IT', { style: 'currency', currency: currency }).format(value);
};

const totalEstimatedPortfolioValue = computed(() => {
    return portfolioStore.portfolios.reduce((total, portfolio) => {
        const portfolioValue = portfolio.user_cards?.reduce((sum, card) => {
            const value = card.estimated_value_cents ? card.estimated_value_cents / 100 : 0;
            return sum + (value * (card.pivot?.quantity || 1));
        }, 0) || 0;
        return total + portfolioValue;
    }, 0);
});

const totalPurchasePortfolioCost = computed(() => {
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

const handlePortfolioCreated = () => {
    console.log('Portfolio created event received on dashboard');
    // Store updates itself, dashboard computed properties will react.
    // Optionally, redirect to the new portfolio or the portfolios index:
    // router.visit(route('portfolios.index'));
};

</script>

<style scoped>
/* Add any page-specific styles here */
</style> 