<template>
  <AuthenticatedLayout>
    <Head title="Dashboard" />

    <div
      class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700"
    >
      <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-4 sm:py-6">
        <div
          class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-3 sm:space-y-0"
        >
          <div>
            <h1
              class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white"
            >
              Dashboard
            </h1>
            <p
              class="text-gray-600 dark:text-gray-400 mt-1 text-sm sm:text-base"
            >
              Welcome back! Here's your portfolio overview.
            </p>
          </div>
          <div class="flex space-x-3" />
        </div>
      </div>
    </div>

    <div class="min-h-screen bg-gray-50 dark:bg-gray-900 py-4 sm:py-8">
      <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
        <!-- Enhanced Metrics Cards -->
        <div
          class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-6 mb-6 sm:mb-8"
        >
          <!-- Total Portfolios Card -->
          <div
            class="bg-white dark:bg-gray-800 rounded-card p-4 sm:p-6 shadow-card border border-gray-200 dark:border-gray-700 hover:shadow-card-hover transition-shadow"
          >
            <div class="flex items-center">
              <div class="p-2 sm:p-3 bg-blue-100 dark:bg-blue-900 rounded-full">
                <svg
                  class="w-5 h-5 sm:w-6 sm:h-6 text-blue-600 dark:text-blue-400"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                  />
                </svg>
              </div>
              <div class="ml-3 sm:ml-4">
                <p
                  class="text-xs sm:text-sm font-medium text-gray-600 dark:text-gray-400"
                >
                  Portfolios
                </p>
                <p
                  class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white"
                >
                  {{ portfolioStore.portfolios.length }}
                </p>
              </div>
            </div>
          </div>

          <!-- Total Cards Card -->
          <div
            class="bg-white dark:bg-gray-800 rounded-card p-4 sm:p-6 shadow-card border border-gray-200 dark:border-gray-700 hover:shadow-card-hover transition-shadow"
          >
            <div class="flex items-center">
              <div
                class="p-2 sm:p-3 bg-green-100 dark:bg-green-900 rounded-full"
              >
                <svg
                  class="w-5 h-5 sm:w-6 sm:h-6 text-green-600 dark:text-green-400"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M9 5H7a2 2 0 00-2 2v6a2 2 0 002 2h2m9-2a2 2 0 01-2 2H7m2-4v1h3m-3 1h7a2 2 0 002-2V7a2 2 0 00-2-2H7z"
                  />
                </svg>
              </div>
              <div class="ml-3 sm:ml-4">
                <p
                  class="text-xs sm:text-sm font-medium text-gray-600 dark:text-gray-400"
                >
                  Total Cards
                </p>
                <p
                  class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white"
                >
                  {{ totalCards }}
                </p>
              </div>
            </div>
          </div>

          <!-- Total Value Card -->
          <div
            class="bg-white dark:bg-gray-800 rounded-card p-4 sm:p-6 shadow-card border border-gray-200 dark:border-gray-700 hover:shadow-card-hover transition-shadow"
          >
            <div class="flex items-center">
              <div
                class="p-2 sm:p-3 bg-yellow-100 dark:bg-yellow-900 rounded-full"
              >
                <svg
                  class="w-5 h-5 sm:w-6 sm:h-6 text-yellow-600 dark:text-yellow-400"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                  />
                </svg>
              </div>
              <div class="ml-3 sm:ml-4">
                <p
                  class="text-xs sm:text-sm font-medium text-gray-600 dark:text-gray-400"
                >
                  Total Value
                </p>
                <p
                  class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white"
                >
                  {{ formatCurrency(totalValue) }}
                </p>
              </div>
            </div>
          </div>

          <!-- Average Value Card -->
          <div
            class="bg-white dark:bg-gray-800 rounded-card p-4 sm:p-6 shadow-card border border-gray-200 dark:border-gray-700 hover:shadow-card-hover transition-shadow"
          >
            <div class="flex items-center">
              <div
                class="p-2 sm:p-3 bg-purple-100 dark:bg-purple-900 rounded-full"
              >
                <svg
                  class="w-5 h-5 sm:w-6 sm:h-6 text-purple-600 dark:text-purple-400"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                  />
                </svg>
              </div>
              <div class="ml-3 sm:ml-4">
                <p
                  class="text-xs sm:text-sm font-medium text-gray-600 dark:text-gray-400"
                >
                  Avg. Card Value
                </p>
                <p
                  class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white"
                >
                  {{ formatCurrency(averageCardValue) }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Quick Actions Section -->
        <div class="mb-6 sm:mb-8">
          <div
            class="bg-white dark:bg-gray-800 rounded-card p-4 sm:p-6 shadow-card border border-gray-200 dark:border-gray-700"
          >
            <h3
              class="text-base sm:text-lg font-semibold text-gray-900 dark:text-white mb-3 sm:mb-4"
            >
              Quick Actions
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
              <button
                class="flex items-center justify-center px-4 py-3 bg-blue-600 text-white rounded-button hover:bg-blue-700 transition-colors text-sm font-medium"
                @click="showCreatePortfolioModal = true"
              >
                <svg
                  class="w-4 h-4 mr-2"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 6v6m0 0v6m0-6h6m-6 0H6"
                  />
                </svg>
                Create Portfolio
              </button>
              <Link
                :href="route('blueprints.index')"
                class="flex items-center justify-center px-4 py-3 bg-green-600 text-white rounded-button hover:bg-green-700 transition-colors text-sm font-medium"
              >
                <svg
                  class="w-4 h-4 mr-2"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                  />
                </svg>
                Browse Cards
              </Link>
              <!-- Card Scanner temporarily hidden - feature not ready yet -->
              <!-- <Link
                :href="route('card-scanner')"
                class="flex items-center justify-center px-4 py-3 bg-purple-600 text-white rounded-button hover:bg-purple-700 transition-colors text-sm font-medium"
              >
                <svg
                  class="w-4 h-4 mr-2"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"
                  />
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"
                  />
                </svg>
                Scan Cards
              </Link> -->
            </div>
          </div>
        </div>

        <!-- Recent Portfolios Section -->
        <div
          class="bg-white dark:bg-gray-800 rounded-card p-4 sm:p-6 shadow-card border border-gray-200 dark:border-gray-700"
        >
          <div
            class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 sm:mb-6 space-y-2 sm:space-y-0"
          >
            <h3
              class="text-base sm:text-lg font-semibold text-gray-900 dark:text-white"
            >
              Recent Portfolios
            </h3>
            <Link
              :href="route('portfolios.index')"
              class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-500 dark:hover:text-blue-300 font-medium"
            >
              View all portfolios →
            </Link>
          </div>

          <div
            v-if="recentPortfolios.length > 0"
            class="space-y-3 sm:space-y-4"
          >
            <div
              v-for="portfolio in recentPortfolios"
              :key="portfolio.id"
              class="flex flex-col sm:flex-row sm:items-center sm:justify-between p-3 sm:p-4 bg-gray-50 dark:bg-gray-700 rounded-lg"
            >
              <div class="flex items-center space-x-3 mb-2 sm:mb-0">
                <div
                  class="w-8 h-8 sm:w-10 sm:h-10 bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg flex items-center justify-center"
                >
                  <span class="text-white font-bold text-xs sm:text-sm">
                    {{ portfolio.name.charAt(0).toUpperCase() }}
                  </span>
                </div>
                <div>
                  <Link
                    :href="route('portfolios.show', portfolio.id)"
                    class="font-medium text-gray-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400 text-sm sm:text-base"
                  >
                    {{ portfolio.name }}
                  </Link>
                  <p
                    class="text-xs sm:text-sm text-gray-500 dark:text-gray-400"
                  >
                    {{ portfolio.user_cards_count }} cards
                  </p>
                </div>
              </div>
              <div class="text-right">
                <div
                  class="text-sm sm:text-base font-semibold text-gray-900 dark:text-white"
                >
                  {{ formatCurrency(calculatePortfolioValue(portfolio)) }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                  Total value
                </div>
              </div>
            </div>
          </div>

          <div v-else class="text-center py-8 sm:py-12">
            <svg
              class="mx-auto h-8 w-8 sm:h-12 sm:w-12 text-gray-400"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
              />
            </svg>
            <h3
              class="mt-2 text-sm sm:text-base font-medium text-gray-900 dark:text-white"
            >
              No portfolios yet
            </h3>
            <p class="mt-1 text-xs sm:text-sm text-gray-500 dark:text-gray-400">
              Get started by creating your first portfolio.
            </p>
            <div class="mt-4 sm:mt-6">
              <button
                type="button"
                class="inline-flex items-center px-3 sm:px-4 py-2 border border-transparent shadow-sm text-xs sm:text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                @click="showCreatePortfolioModal = true"
              >
                <svg
                  class="-ml-1 mr-2 h-4 w-4"
                  xmlns="http://www.w3.org/2000/svg"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 6v6m0 0v6m0-6h6m-6 0H6"
                  />
                </svg>
                Create Portfolio
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Create Portfolio Modal -->
    <CreatePortfolioModal
      :is-open="showCreatePortfolioModal"
      @close="showCreatePortfolioModal = false"
    />

    <!-- Price Alerts Modal (Placeholder for now) -->
    <div v-if="showPriceAlertsModal" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="flex min-h-screen items-center justify-center p-4">
        <div
          class="fixed inset-0 bg-black bg-opacity-25"
          @click="showPriceAlertsModal = false"
        />
        <div
          class="relative bg-white dark:bg-gray-800 rounded-card p-6 max-w-md w-full border border-gray-200 dark:border-gray-700"
        >
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            Price Alerts
          </h3>
          <p class="text-gray-600 dark:text-gray-400 mb-4">
            Price alert functionality coming soon! We'll notify you when your
            cards' values change significantly.
          </p>
          <button
            class="w-full px-4 py-2 bg-gray-600 text-white rounded-button hover:bg-gray-700 transition-colors"
            @click="showPriceAlertsModal = false"
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
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import { usePortfolioStore } from '../../stores/portfolioStore';
import CreatePortfolioModal from '../../Components/CreatePortfolioModal.vue';

const portfolioStore = usePortfolioStore();

// Reactive variables
const showCreatePortfolioModal = ref(false);
const showPriceAlertsModal = ref(false);

onMounted(async () => {
  // Fetch all portfolios to calculate totals.
  // This might already be called if navigating from login, but good to ensure.
  if (portfolioStore.portfolios.length === 0) {
    await portfolioStore.fetchPortfolios();
  }
});

// Methods
const formatCurrency = (value: number | null | undefined, currency = 'EUR') => {
  if (value === null || typeof value === 'undefined') return 'N/A';
  return new Intl.NumberFormat('it-IT', {
    style: 'currency',
    currency: currency,
  }).format(value);
};

// Computed properties for financial metrics
const totalEstimatedPortfolioValue = computed(() => {
  if (!Array.isArray(portfolioStore.portfolios)) {
    return 0;
  }
  return portfolioStore.portfolios.reduce((total, portfolio) => {
    const portfolioValue =
      portfolio.user_cards?.reduce((sum, card) => {
        const value = card.estimated_value_cents
          ? card.estimated_value_cents / 100
          : 0;
        return sum + value * (card.pivot?.quantity || 1);
      }, 0) || 0;
    return total + portfolioValue;
  }, 0);
});

const totalPurchasePortfolioCost = computed(() => {
  if (!Array.isArray(portfolioStore.portfolios)) {
    return 0;
  }
  return portfolioStore.portfolios.reduce((total, portfolio) => {
    const portfolioCost =
      portfolio.user_cards?.reduce((sum, card) => {
        const cost = card.purchase_price_cents
          ? card.purchase_price_cents / 100
          : 0;
        return sum + cost * (card.pivot?.quantity || 1);
      }, 0) || 0;
    return total + portfolioCost;
  }, 0);
});

const totalProfitLoss = computed(() => {
  return totalEstimatedPortfolioValue.value - totalPurchasePortfolioCost.value;
});

// Computed properties for portfolio statistics
const totalCards = computed(() => {
  if (!Array.isArray(portfolioStore.portfolios)) {
    return 0;
  }

  return portfolioStore.portfolios.reduce((total, portfolio) => {
    const portfolioCardCount = portfolio.user_cards?.reduce((sum, card) => {
      return sum + (card.pivot?.quantity || 1);
    }, 0);
    return total + (portfolioCardCount || 0);
  }, 0);
});

const totalValue = computed(() => {
  return totalEstimatedPortfolioValue.value;
});

const averageCardValue = computed(() => {
  const cards = totalCards.value;
  return cards > 0 ? totalEstimatedPortfolioValue.value / cards : 0;
});

// Helper function to calculate portfolio value
const calculatePortfolioValue = (portfolio: any) => {
  if (!portfolio.user_cards || !Array.isArray(portfolio.user_cards)) {
    return 0;
  }
  return portfolio.user_cards.reduce((total: number, card: any) => {
    const value = card.estimated_value_cents
      ? card.estimated_value_cents / 100
      : 0;
    return total + value * (card.pivot?.quantity || 1);
  }, 0);
};

// Computed property for recent portfolios
const recentPortfolios = computed(() => {
  return portfolioStore.portfolios.slice(0, 5); // Show up to 5 recent portfolios
});
</script>

<style scoped>
/* Add any page-specific styles here */
</style>
