<template>
  <AuthenticatedLayout>
    <Head title="Dashboard" />

    <div
      class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700"
    >
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
              Dashboard
            </h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">
              Welcome back! Here's your portfolio overview.
            </p>
          </div>
          <div class="flex space-x-3" />
        </div>
      </div>
    </div>

    <div class="min-h-screen bg-gray-50 dark:bg-gray-900 py-8">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Enhanced Metrics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
          <!-- Total Estimated Value -->
          <div
            class="bg-white dark:bg-gray-800 rounded-card p-6 border border-gray-200 dark:border-gray-700 shadow-card hover:shadow-card-hover transition-shadow duration-200"
          >
            <div class="flex items-center justify-between">
              <div class="flex-1">
                <p
                  class="text-gray-600 dark:text-gray-400 text-sm font-medium uppercase tracking-wide"
                >
                  Total Estimated Value
                </p>
                <p
                  class="text-2xl font-bold text-gray-900 dark:text-white mt-2"
                >
                  {{ formatCurrency(totalEstimatedPortfolioValue) }}
                </p>
                <div class="flex items-center mt-2">
                  <svg
                    class="h-3 w-3 text-gray-500"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"
                    />
                  </svg>
                  <span class="text-gray-600 dark:text-gray-400 text-xs ml-1"
                    >Portfolio Value</span
                  >
                </div>
              </div>
              <div
                class="h-10 w-10 bg-gray-600 rounded-card flex items-center justify-center"
              >
                <svg
                  class="h-5 w-5 text-white"
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
            </div>
          </div>

          <!-- Total Purchase Cost -->
          <div
            class="bg-white dark:bg-gray-800 rounded-card p-6 border border-gray-200 dark:border-gray-700 shadow-card hover:shadow-card-hover transition-shadow duration-200"
          >
            <div class="flex items-center justify-between">
              <div class="flex-1">
                <p
                  class="text-gray-600 dark:text-gray-400 text-sm font-medium uppercase tracking-wide"
                >
                  Total Purchase Cost
                </p>
                <p
                  class="text-2xl font-bold text-gray-900 dark:text-white mt-2"
                >
                  {{ formatCurrency(totalPurchasePortfolioCost) }}
                </p>
                <div class="flex items-center mt-2">
                  <svg
                    class="h-3 w-3 text-gray-500"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"
                    />
                  </svg>
                  <span class="text-gray-600 dark:text-gray-400 text-xs ml-1"
                    >Initial Investment</span
                  >
                </div>
              </div>
              <div
                class="h-10 w-10 bg-gray-600 rounded-card flex items-center justify-center"
              >
                <svg
                  class="h-5 w-5 text-white"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"
                  />
                </svg>
              </div>
            </div>
          </div>

          <!-- Total Profit/Loss -->
          <div
            class="bg-white dark:bg-gray-800 rounded-card p-6 border border-gray-200 dark:border-gray-700 shadow-card hover:shadow-card-hover transition-shadow duration-200"
          >
            <div class="flex items-center justify-between">
              <div class="flex-1">
                <p
                  class="text-gray-600 dark:text-gray-400 text-sm font-medium uppercase tracking-wide"
                >
                  Total Profit / Loss
                </p>
                <p
                  class="text-2xl font-bold text-gray-900 dark:text-white mt-2"
                >
                  {{ formatCurrency(totalProfitLoss) }}
                </p>
                <div class="flex items-center mt-2">
                  <svg
                    v-if="totalProfitLoss >= 0"
                    class="h-3 w-3 text-green-600"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"
                    />
                  </svg>
                  <svg
                    v-else
                    class="h-3 w-3 text-red-600"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"
                    />
                  </svg>
                  <span
                    class="text-xs ml-1"
                    :class="
                      totalProfitLoss >= 0 ? 'text-green-600' : 'text-red-600'
                    "
                  >
                    {{ totalProfitLoss >= 0 ? 'Profit' : 'Loss' }}
                  </span>
                </div>
              </div>
              <div
                class="h-10 w-10 rounded-card flex items-center justify-center"
                :class="totalProfitLoss >= 0 ? 'bg-green-600' : 'bg-red-600'"
              >
                <svg
                  v-if="totalProfitLoss >= 0"
                  class="h-5 w-5 text-white"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M7 11l5-5m0 0l5 5m-5-5v12"
                  />
                </svg>
                <svg
                  v-else
                  class="h-5 w-5 text-white"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M17 13l-5 5m0 0l-5-5m5 5V6"
                  />
                </svg>
              </div>
            </div>
          </div>
        </div>

        <!-- Quick Actions Hub -->
        <div
          class="bg-white dark:bg-gray-800 rounded-card p-6 shadow-card mb-8 border border-gray-200 dark:border-gray-700"
        >
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            Quick Actions
          </h3>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <button
              class="group flex flex-col items-center p-4 rounded-card bg-gray-50 dark:bg-gray-700/50 hover:bg-gray-100 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-600 transition-colors duration-200"
              @click="showCreatePortfolioModal = true"
            >
              <div
                class="h-8 w-8 bg-gray-600 rounded-button flex items-center justify-center mb-2 group-hover:bg-gray-700 transition-colors"
              >
                <svg
                  class="h-4 w-4 text-white"
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
              </div>
              <span class="text-xs font-medium text-gray-700 dark:text-gray-300"
                >New Portfolio</span
              >
              <span class="text-xs text-gray-500 dark:text-gray-400 mt-1"
                >Create collection</span
              >
            </button>

            <Link
              :href="route('portfolios.index')"
              class="group flex flex-col items-center p-4 rounded-card bg-gray-50 dark:bg-gray-700/50 hover:bg-gray-100 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-600 transition-colors duration-200"
            >
              <div
                class="h-8 w-8 bg-gray-600 rounded-button flex items-center justify-center mb-2 group-hover:bg-gray-700 transition-colors"
              >
                <svg
                  class="h-4 w-4 text-white"
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
              <span class="text-xs font-medium text-gray-700 dark:text-gray-300"
                >View Portfolios</span
              >
              <span class="text-xs text-gray-500 dark:text-gray-400 mt-1"
                >Manage collections</span
              >
            </Link>

            <Link
              :href="route('blueprints.index')"
              class="group flex flex-col items-center p-4 rounded-card bg-gray-50 dark:bg-gray-700/50 hover:bg-gray-100 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-600 transition-colors duration-200"
            >
              <div
                class="h-8 w-8 bg-gray-600 rounded-button flex items-center justify-center mb-2 group-hover:bg-gray-700 transition-colors"
              >
                <svg
                  class="h-4 w-4 text-white"
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
              </div>
              <span class="text-xs font-medium text-gray-700 dark:text-gray-300"
                >Search Cards</span
              >
              <span class="text-xs text-gray-500 dark:text-gray-400 mt-1"
                >Browse catalog</span
              >
            </Link>

            <button
              class="group flex flex-col items-center p-4 rounded-card bg-gray-50 dark:bg-gray-700/50 hover:bg-gray-100 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-600 transition-colors duration-200"
              @click="showPriceAlertsModal = true"
            >
              <div
                class="h-8 w-8 bg-gray-600 rounded-button flex items-center justify-center mb-2 group-hover:bg-gray-700 transition-colors"
              >
                <svg
                  class="h-4 w-4 text-white"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M15 17h5l-5 5-5-5h5zm-5-5l5-5 5 5h-5v-5z"
                  />
                </svg>
              </div>
              <span class="text-xs font-medium text-gray-700 dark:text-gray-300"
                >Price Alerts</span
              >
              <span class="text-xs text-gray-500 dark:text-gray-400 mt-1"
                >Track changes</span
              >
            </button>
          </div>
        </div>

        <!-- Two Column Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
          <!-- Left Column - Recent Activity -->
          <div class="lg:col-span-2">
            <div
              class="bg-white dark:bg-gray-800 rounded-card p-6 shadow-card border border-gray-200 dark:border-gray-700"
            >
              <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                  Recent Activity
                </h3>
                <Link
                  :href="route('portfolios.index')"
                  class="text-gray-600 dark:text-gray-400 text-sm hover:text-gray-800 dark:hover:text-gray-300 font-medium"
                >
                  View All →
                </Link>
              </div>

              <div v-if="recentActivities.length > 0" class="space-y-4">
                <div
                  v-for="activity in recentActivities"
                  :key="activity.id"
                  class="flex items-center space-x-4 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                >
                  <div
                    class="h-10 w-10 rounded-full flex items-center justify-center"
                    :class="activity.iconBg"
                  >
                    <svg
                      class="h-5 w-5"
                      :class="activity.iconColor"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        :d="activity.iconPath"
                      />
                    </svg>
                  </div>
                  <div class="flex-1 min-w-0">
                    <p
                      class="text-sm font-medium text-gray-900 dark:text-white truncate"
                    >
                      {{ activity.title }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                      {{ activity.time }}
                    </p>
                  </div>
                  <div class="text-right">
                    <span
                      v-if="activity.value"
                      class="text-sm font-semibold"
                      :class="activity.valueColor"
                      >{{ activity.value }}</span
                    >
                  </div>
                </div>
              </div>

              <div v-else class="text-center py-12">
                <svg
                  class="h-12 w-12 text-gray-400 dark:text-gray-600 mx-auto mb-4"
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
                <h4
                  class="text-lg font-medium text-gray-900 dark:text-white mb-2"
                >
                  No Recent Activity
                </h4>
                <p class="text-gray-500 dark:text-gray-400 mb-4">
                  Start by creating a portfolio or adding cards to see activity
                  here.
                </p>
                <button
                  class="px-4 py-2 bg-gray-600 text-white rounded-button hover:bg-gray-700 transition-colors"
                  @click="showCreatePortfolioModal = true"
                >
                  Create Portfolio
                </button>
              </div>
            </div>
          </div>

          <!-- Right Column - Portfolio Summary -->
          <div class="space-y-4">
            <!-- Portfolio Count -->
            <div
              class="bg-white dark:bg-gray-800 rounded-card p-6 shadow-card border border-gray-200 dark:border-gray-700"
            >
              <div class="flex items-center justify-between mb-4">
                <h4 class="text-lg font-semibold text-gray-900 dark:text-white">
                  Portfolios
                </h4>
                <span class="text-xl font-bold text-gray-900 dark:text-white">{{
                  portfolioStore.portfolios.length
                }}</span>
              </div>
              <div class="space-y-2">
                <div class="flex justify-between text-sm">
                  <span class="text-gray-600 dark:text-gray-400"
                    >Total Cards</span
                  >
                  <span class="font-medium text-gray-900 dark:text-white">{{
                    totalCardsFromStore
                  }}</span>
                </div>
                <div class="flex justify-between text-sm">
                  <span class="text-gray-600 dark:text-gray-400"
                    >Avg. Portfolio Value</span
                  >
                  <span class="font-medium text-gray-900 dark:text-white">{{
                    formatCurrency(averagePortfolioValue)
                  }}</span>
                </div>
              </div>
            </div>

            <!-- Chart Placeholder -->
            <div
              class="bg-white dark:bg-gray-800 rounded-card p-6 shadow-card border border-gray-200 dark:border-gray-700"
            >
              <h4
                class="text-lg font-semibold text-gray-900 dark:text-white mb-4"
              >
                Performance
              </h4>
              <div
                class="h-32 bg-gray-100 dark:bg-gray-700 rounded-card flex items-center justify-center"
              >
                <div class="text-center">
                  <svg
                    class="h-6 w-6 text-gray-400 dark:text-gray-600 mx-auto mb-2"
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
                  <p class="text-xs text-gray-500 dark:text-gray-400">
                    Chart Coming Soon
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Getting Started Guide (if no portfolios) -->
        <div
          v-if="
            portfolioStore.portfolios.length === 0 && !portfolioStore.isLoading
          "
          class="bg-white dark:bg-gray-800 rounded-card p-8 shadow-card border border-gray-200 dark:border-gray-700 mt-8"
        >
          <div class="text-center">
            <svg
              class="h-12 w-12 text-gray-600 mx-auto mb-4"
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
            <h3
              class="text-xl font-semibold text-gray-900 dark:text-white mb-2"
            >
              Welcome to ItsMint!
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-6 max-w-2xl mx-auto">
              Start building your One Piece TCG collection by creating your
              first portfolio. Track card values, monitor performance, and
              organize your collection like a pro.
            </p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
              <button
                class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white font-medium rounded-button transition-colors"
                @click="showCreatePortfolioModal = true"
              >
                Create Your First Portfolio
              </button>
              <Link
                :href="route('blueprints.index')"
                class="px-4 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 font-medium rounded-button transition-colors"
              >
                Browse Card Catalog
              </Link>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modals -->
    <CreatePortfolioModal
      :is-open="showCreatePortfolioModal"
      @close="showCreatePortfolioModal = false"
      @created="handlePortfolioCreated"
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

  // Debug: log the portfolios data
  console.log('Dashboard portfolios data:', portfolioStore.portfolios);
  if (
    portfolioStore.portfolios.length > 0 &&
    portfolioStore.portfolios[0].user_cards
  ) {
    console.log(
      'First portfolio user_cards:',
      portfolioStore.portfolios[0].user_cards
    );
    if (portfolioStore.portfolios[0].user_cards.length > 0) {
      console.log(
        'First card estimated_value_cents:',
        portfolioStore.portfolios[0].user_cards[0].estimated_value_cents
      );
    }
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
const totalCardsFromStore = computed(() => {
  if (!Array.isArray(portfolioStore.portfolios)) {
    return 0;
  }
  return portfolioStore.portfolios.reduce((total, portfolio) => {
    const portfolioCardCount = portfolio.user_cards?.reduce((sum, card) => {
      return sum + (card.pivot?.quantity || 1);
    }, 0) || 0;
    return total + portfolioCardCount;
  }, 0);
});

const averagePortfolioValue = computed(() => {
  const portfolioCount = portfolioStore.portfolios.length;
  return portfolioCount > 0
    ? totalEstimatedPortfolioValue.value / portfolioCount
    : 0;
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
      iconPath: 'M12 6v6m0 0v6m0-6h6m-6 0H6',
    },
    {
      id: 2,
      title: 'Portfolio "Championship Deck" created',
      time: '1 day ago',
      value: null,
      valueColor: '',
      iconBg: 'bg-blue-100 dark:bg-blue-900/30',
      iconColor: 'text-blue-600',
      iconPath:
        'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
    },
    {
      id: 3,
      title: 'Trafalgar Law price updated',
      time: '2 days ago',
      value: '+5.20 €',
      valueColor: 'text-green-600',
      iconBg: 'bg-orange-100 dark:bg-orange-900/30',
      iconColor: 'text-orange-600',
      iconPath: 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6',
    },
  ].slice(0, portfolioStore.portfolios.length > 0 ? 3 : 0); // Show activities only if user has portfolios
});
</script>

<style scoped>
/* Add any page-specific styles here */
</style>
