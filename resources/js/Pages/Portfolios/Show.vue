<template>
  <AuthenticatedLayout>
    <Head :title="portfolio.name" />

    <!-- Header Section -->
    <div
      class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700"
    >
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div
          class="flex flex-col sm:flex-row sm:items-center justify-between gap-4"
        >
          <div class="flex-1 min-w-0">
            <nav class="flex mb-4" aria-label="Breadcrumb">
              <ol
                class="flex items-center space-x-2 text-sm text-gray-500 dark:text-gray-400"
              >
                <li>
                  <a
                    :href="route('portfolios.index')"
                    class="hover:text-gray-700 dark:hover:text-gray-300 transition-colors"
                  >
                    Portfolios
                  </a>
                </li>
                <li class="flex items-center">
                  <svg
                    class="h-4 w-4 mx-2 text-gray-400"
                    fill="currentColor"
                    viewBox="0 0 20 20"
                  >
                    <path
                      fill-rule="evenodd"
                      d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                      clip-rule="evenodd"
                    />
                  </svg>
                  <span class="font-medium text-gray-900 dark:text-gray-100">{{
                    portfolio.name
                  }}</span>
                </li>
              </ol>
            </nav>
            <h1
              class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-2"
            >
              {{ portfolio.name }}
            </h1>
            <p
              v-if="portfolio.description"
              class="text-gray-600 dark:text-gray-400 max-w-2xl"
            >
              {{ portfolio.description }}
            </p>
          </div>
          <div class="flex flex-col sm:flex-row gap-3">
            <button
              class="inline-flex items-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg shadow-sm transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
              @click="openAddCardModal"
            >
              <svg
                class="h-5 w-5 mr-2"
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
              Add Cards
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="py-8">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="space-y-8">
          <!-- Portfolio Stats -->
          <!-- <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
              <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Portfolio Overview</h2>
            </div>
            <div class="p-6">
              <div class="stats-grid grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="text-center">
                  <div class="inline-flex items-center justify-center w-12 h-12 bg-blue-100 dark:bg-blue-900/30 rounded-lg mb-3">
                    <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                  </div>
                  <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    {{ portfolio.user_cards?.length || 0 }}
                  </div>
                  <div class="text-sm text-gray-500 dark:text-gray-400">Total Cards</div>
                </div>
                
                <div class="text-center">
                  <div class="inline-flex items-center justify-center w-12 h-12 bg-green-100 dark:bg-green-900/30 rounded-lg mb-3">
                    <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                  </div>
                  <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    {{ formatCurrency(totalEstimatedValue) }}
                  </div>
                  <div class="text-sm text-gray-500 dark:text-gray-400">Estimated Value</div>
                </div>
                
                <div class="text-center">
                  <div class="inline-flex items-center justify-center w-12 h-12 bg-purple-100 dark:bg-purple-900/30 rounded-lg mb-3">
                    <svg class="h-6 w-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                    </svg>
                  </div>
                  <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    {{ formatCurrency(totalPurchaseCost) }}
                  </div>
                  <div class="text-sm text-gray-500 dark:text-gray-400">Purchase Cost</div>
                </div>
                
                <div class="text-center">
                  <div class="inline-flex items-center justify-center w-12 h-12 rounded-lg mb-3"
                       :class="profitLoss >= 0 ? 'bg-emerald-100 dark:bg-emerald-900/30' : 'bg-red-100 dark:bg-red-900/30'">
                    <svg class="h-6 w-6" :class="profitLoss >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path v-if="profitLoss >= 0" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12" />
                      <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6" />
                    </svg>
                  </div>
                  <div class="text-2xl font-bold" :class="profitLoss >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                    {{ formatCurrency(Math.abs(profitLoss)) }}
                  </div>
                  <div class="text-sm text-gray-500 dark:text-gray-400">
                    {{ profitLoss >= 0 ? 'Profit' : 'Loss' }}
                  </div>
                </div>
              </div>
            </div>
          </div> -->

          <!-- Portfolio Performance Dashboard -->
          <div
            v-if="
              props.portfolio.user_cards &&
              props.portfolio.user_cards.length > 0
            "
            class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 overflow-hidden"
          >
            <!-- Header Section -->
            <div
              class="bg-gradient-to-r from-slate-50 to-gray-50 dark:from-gray-800 dark:to-gray-700 px-8 py-6 border-b border-gray-200 dark:border-gray-600"
            >
              <div class="flex items-center justify-between mb-6">
                <div>
                  <h2
                    class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight"
                  >
                    Portfolio Performance
                  </h2>
                  <p class="text-gray-600 dark:text-gray-400 text-sm mt-1">
                    Track your investment performance over time
                  </p>
                </div>
                <div
                  class="flex items-center space-x-2 text-sm text-gray-500 dark:text-gray-400"
                >
                  <svg
                    class="h-4 w-4"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                  </svg>
                  <span
                    >Last updated: {{ new Date().toLocaleDateString() }}</span
                  >
                </div>
              </div>

              <!-- Key Metrics Grid -->
              <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <!-- Market Value -->
                <TooltipHelper
                  content="The current market value of your portfolio based on the latest card prices. This represents what your collection would be worth if you sold it at current market rates."
                  position="bottom"
                >
                  <div
                    class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-sm border border-gray-100 dark:border-gray-700 relative overflow-hidden"
                  >
                    <div
                      class="absolute top-0 left-0 w-1 h-full bg-green-500"
                    />
                    <div class="flex items-center justify-between mb-2">
                      <span
                        class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                        >Market Value</span
                      >
                      <div
                        class="p-1.5 bg-green-100 dark:bg-green-900/30 rounded-lg"
                      >
                        <svg
                          class="h-4 w-4 text-green-600 dark:text-green-400"
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
                      </div>
                    </div>
                    <div
                      class="text-2xl font-bold text-gray-900 dark:text-white"
                    >
                      {{ overallStatistics.marketValue }}
                    </div>
                  </div>
                </TooltipHelper>

                <!-- Cost Basis -->
                <TooltipHelper
                  content="The total amount of money you invested to purchase all the cards in your portfolio. This is your initial investment before any gains or losses."
                  position="bottom"
                >
                  <div
                    class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-sm border border-gray-100 dark:border-gray-700 relative overflow-hidden"
                  >
                    <div class="absolute top-0 left-0 w-1 h-full bg-blue-500" />
                    <div class="flex items-center justify-between mb-2">
                      <span
                        class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                        >Cost Basis</span
                      >
                      <div
                        class="p-1.5 bg-blue-100 dark:bg-blue-900/30 rounded-lg"
                      >
                        <svg
                          class="h-4 w-4 text-blue-600 dark:text-blue-400"
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
                    <div
                      class="text-2xl font-bold text-gray-900 dark:text-white"
                    >
                      {{ overallStatistics.costBasis }}
                    </div>
                  </div>
                </TooltipHelper>

                <!-- Unrealized P&L -->
                <TooltipHelper
                  content="Unrealized Profit & Loss - the difference between your portfolio's current market value and what you paid for it. This represents potential gains or losses you would realize if you sold today."
                  position="bottom"
                >
                  <div
                    :class="[
                      'bg-white dark:bg-gray-800 rounded-xl p-5 shadow-sm border border-gray-100 dark:border-gray-700 relative overflow-hidden',
                    ]"
                  >
                    <div
                      :class="[
                        'absolute top-0 left-0 w-1 h-full',
                        overallStatistics.unrealizedGainPositive
                          ? 'bg-emerald-500'
                          : 'bg-red-500',
                      ]"
                    />
                    <div class="flex items-center justify-between mb-2">
                      <span
                        class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                        >Unrealized P&L</span
                      >
                      <div
                        :class="[
                          'p-1.5 rounded-lg',
                          overallStatistics.unrealizedGainPositive
                            ? 'bg-emerald-100 dark:bg-emerald-900/30'
                            : 'bg-red-100 dark:bg-red-900/30',
                        ]"
                      >
                        <svg
                          :class="[
                            'h-4 w-4',
                            overallStatistics.unrealizedGainPositive
                              ? 'text-emerald-600 dark:text-emerald-400'
                              : 'text-red-600 dark:text-red-400',
                          ]"
                          fill="none"
                          stroke="currentColor"
                          viewBox="0 0 24 24"
                        >
                          <path
                            v-if="overallStatistics.unrealizedGainPositive"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M7 11l5-5m0 0l5 5m-5-5v12"
                          />
                          <path
                            v-else
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M17 13l-5 5m0 0l-5-5m5 5V6"
                          />
                        </svg>
                      </div>
                    </div>
                    <div
                      :class="[
                        'text-2xl font-bold',
                        overallStatistics.unrealizedGainPositive
                          ? 'text-emerald-600 dark:text-emerald-400'
                          : 'text-red-600 dark:text-red-400',
                      ]"
                    >
                      {{ overallStatistics.unrealizedGainPositive ? '+' : '-'
                      }}{{ overallStatistics.unrealizedGain }}
                    </div>
                  </div>
                </TooltipHelper>

                <!-- ROI -->
                <TooltipHelper
                  content="Return on Investment - the percentage gain or loss of your portfolio relative to your initial investment. It shows how well your investment is performing as a percentage of what you originally spent."
                  position="bottom"
                >
                  <div
                    :class="[
                      'bg-white dark:bg-gray-800 rounded-xl p-5 shadow-sm border border-gray-100 dark:border-gray-700 relative overflow-hidden',
                    ]"
                  >
                    <div
                      :class="[
                        'absolute top-0 left-0 w-1 h-full',
                        overallStatistics.performancePositive
                          ? 'bg-purple-500'
                          : 'bg-orange-500',
                      ]"
                    />
                    <div class="flex items-center justify-between mb-2">
                      <span
                        class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                        >ROI</span
                      >
                      <div
                        :class="[
                          'p-1.5 rounded-lg',
                          overallStatistics.performancePositive
                            ? 'bg-purple-100 dark:bg-purple-900/30'
                            : 'bg-orange-100 dark:bg-orange-900/30',
                        ]"
                      >
                        <svg
                          :class="[
                            'h-4 w-4',
                            overallStatistics.performancePositive
                              ? 'text-purple-600 dark:text-purple-400'
                              : 'text-orange-600 dark:text-orange-400',
                          ]"
                          fill="none"
                          stroke="currentColor"
                          viewBox="0 0 24 24"
                        >
                          <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2-2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                          />
                        </svg>
                      </div>
                    </div>
                    <div
                      :class="[
                        'text-2xl font-bold',
                        overallStatistics.performancePositive
                          ? 'text-purple-600 dark:text-purple-400'
                          : 'text-orange-600 dark:text-orange-400',
                      ]"
                    >
                      {{ overallStatistics.performancePositive ? '+' : ''
                      }}{{ overallStatistics.performance }}
                    </div>
                  </div>
                </TooltipHelper>
              </div>
            </div>

            <!-- Chart Section -->
            <div class="px-8 py-6">
              <!-- Chart Controls -->
              <div
                class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6"
              >
                <!-- Metric Selector -->
                <div
                  class="flex items-center space-x-1 bg-gray-100 dark:bg-gray-700 rounded-xl p-1"
                >
                  <button
                    v-for="metric in metrics"
                    :key="metric.key"
                    :class="[
                      'relative px-4 py-2.5 text-sm font-semibold rounded-lg transition-all duration-200 flex items-center space-x-2',
                      activeMetric === metric.key
                        ? 'bg-white dark:bg-gray-600 text-gray-900 dark:text-white shadow-sm border border-gray-200 dark:border-gray-500'
                        : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white hover:bg-white/50 dark:hover:bg-gray-600/50',
                    ]"
                    @click="
                      fetchPortfolioValueHistory(activePeriod, metric.key)
                    "
                  >
                    <div
                      :class="[
                        'w-3 h-3 rounded-full',
                        `bg-gradient-to-r ${metric.key === 'market_value' ? 'from-green-400 to-green-600' : metric.key === 'cost_basis' ? 'from-blue-400 to-blue-600' : 'from-purple-400 to-purple-600'}`,
                      ]"
                    />
                    <span>{{ metric.label }}</span>
                  </button>
                </div>

                <!-- Period Selector -->
                <div
                  class="flex items-center space-x-1 bg-gray-100 dark:bg-gray-700 rounded-xl p-1"
                >
                  <button
                    v-for="period in timePeriods"
                    :key="period"
                    :class="[
                      'px-3 py-2 text-sm font-semibold rounded-lg transition-all duration-200',
                      activePeriod === period
                        ? 'bg-white dark:bg-gray-600 text-gray-900 dark:text-white shadow-sm'
                        : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white hover:bg-white/50 dark:hover:bg-gray-600/50',
                    ]"
                    @click="fetchPortfolioValueHistory(period, activeMetric)"
                  >
                    {{
                      period === '1D'
                        ? '1G'
                        : period === '1W'
                          ? '1S'
                          : period === '1Y'
                            ? '1A'
                            : period
                    }}
                  </button>
                </div>
              </div>

              <!-- Current Value Display -->
              <div
                class="mb-6 p-6 bg-gradient-to-r from-gray-50 to-white dark:from-gray-700 dark:to-gray-600 rounded-xl border border-gray-200 dark:border-gray-600"
              >
                <div class="flex items-center justify-between">
                  <div>
                    <h3
                      class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-1"
                    >
                      Current
                      {{ metrics.find((m) => m.key === activeMetric)?.label }}
                    </h3>
                    <div
                      class="text-4xl font-bold text-gray-900 dark:text-white"
                    >
                      {{ portfolioStatistics.currentValue }}
                    </div>
                  </div>
                  <div class="text-right">
                    <div
                      class="text-sm text-gray-500 dark:text-gray-400 uppercase tracking-wide font-medium mb-1"
                    >
                      {{
                        activePeriod === '1D'
                          ? 'Today'
                          : activePeriod === '1W'
                            ? 'This Week'
                            : activePeriod === '1M'
                              ? 'This Month'
                              : activePeriod === '3M'
                                ? 'Last 3M'
                                : activePeriod === '1Y'
                                  ? 'This Year'
                                  : 'All Time'
                      }}
                    </div>
                    <div class="flex items-center space-x-3">
                      <div
                        :class="[
                          'text-lg font-bold flex items-center space-x-1',
                          portfolioStatistics.positive
                            ? 'text-emerald-600'
                            : 'text-red-600',
                        ]"
                      >
                        <svg
                          :class="[
                            'h-5 w-5',
                            portfolioStatistics.positive
                              ? 'text-emerald-500'
                              : 'text-red-500',
                          ]"
                          fill="none"
                          stroke="currentColor"
                          viewBox="0 0 24 24"
                        >
                          <path
                            v-if="portfolioStatistics.positive"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M7 11l5-5m0 0l5 5m-5-5v12"
                          />
                          <path
                            v-else
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M17 13l-5 5m0 0l-5-5m5 5V6"
                          />
                        </svg>
                        <span
                          >{{ portfolioStatistics.positive ? '+' : '-'
                          }}{{ portfolioStatistics.change }}</span
                        >
                      </div>
                      <div
                        :class="[
                          'text-sm font-semibold px-2 py-1 rounded-lg',
                          portfolioStatistics.positive
                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                            : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                        ]"
                      >
                        {{ portfolioStatistics.positive ? '+' : ''
                        }}{{ portfolioStatistics.percentage }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Chart Container -->
            <div
              class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-600 overflow-hidden"
            >
              <div
                v-if="isLoadingChart"
                class="h-80 flex items-center justify-center"
              >
                <div class="text-center">
                  <div class="relative">
                    <div
                      class="w-16 h-16 border-4 border-gray-200 dark:border-gray-700 border-t-indigo-600 rounded-full animate-spin mx-auto mb-4"
                    />
                    <div
                      class="absolute inset-0 flex items-center justify-center"
                    >
                      <svg
                        class="h-6 w-6 text-indigo-600"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2-2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                        />
                      </svg>
                    </div>
                  </div>
                  <h3
                    class="text-lg font-semibold text-gray-900 dark:text-white mb-2"
                  >
                    Loading Performance Data
                  </h3>
                  <p class="text-gray-500 dark:text-gray-400 text-sm">
                    Calculating portfolio metrics...
                  </p>
                </div>
              </div>

              <div
                v-else-if="
                  portfolioChartData[`${activePeriod}_${activeMetric}`]?.data
                "
                class="h-80 p-4"
              >
                <PriceHistoryChart
                  :chart-data="
                    portfolioChartData[`${activePeriod}_${activeMetric}`].data
                  "
                  :chart-options="{
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                      padding: {
                        top: 20,
                        right: 20,
                        bottom: 20,
                        left: 20,
                      },
                    },
                    plugins: {
                      legend: {
                        display: false,
                      },
                      tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(17, 24, 39, 0.95)',
                        titleColor: 'white',
                        bodyColor: 'white',
                        borderColor:
                          portfolioChartData[`${activePeriod}_${activeMetric}`]
                            ?.data?.datasets[0]?.borderColor ||
                          'rgba(34, 197, 94, 1)',
                        borderWidth: 2,
                        cornerRadius: 12,
                        displayColors: false,
                        titleFont: {
                          size: 14,
                          weight: 'bold',
                        },
                        bodyFont: {
                          size: 13,
                        },
                        padding: 12,
                        callbacks: {
                          title: function (context: any) {
                            return context[0].label;
                          },
                          label: function (context: any) {
                            if (activeMetric === 'performance') {
                              return context.parsed.y.toFixed(1) + '%';
                            }
                            return (
                              '€' +
                              context.parsed.y.toLocaleString('it-IT', {
                                minimumFractionDigits: 2,
                              })
                            );
                          },
                        },
                      },
                    },
                    scales: {
                      x: {
                        display: true,
                        grid: {
                          display: false,
                        },
                        ticks: {
                          color: 'rgba(156, 163, 175, 0.8)',
                          font: {
                            size: 12,
                            weight: '500',
                          },
                          maxTicksLimit: 8,
                        },
                        border: {
                          display: false,
                        },
                      },
                      y: {
                        display: true,
                        position: 'right',
                        grid: {
                          color: 'rgba(229, 231, 235, 0.3)',
                          drawBorder: false,
                        },
                        ticks: {
                          color: 'rgba(156, 163, 175, 0.8)',
                          font: {
                            size: 12,
                            weight: '500',
                          },
                          callback:
                            portfolioChartData[
                              `${activePeriod}_${activeMetric}`
                            ]?.data?.yAxisCallback ||
                            function (value: any) {
                              if (value >= 1000) {
                                return '€' + (value / 1000).toFixed(1) + 'K';
                              }
                              return '€' + value.toFixed(0);
                            },
                          maxTicksLimit: 8,
                        },
                        border: {
                          display: false,
                        },
                      },
                    },
                    elements: {
                      line: {
                        tension: 0.3,
                        borderWidth: 3,
                      },
                      point: {
                        radius: 0,
                        hoverRadius: 8,
                        hoverBorderWidth: 3,
                        hoverBackgroundColor: 'white',
                      },
                    },
                    interaction: {
                      intersect: false,
                      mode: 'index',
                    },
                  }"
                />
              </div>

              <div
                v-else-if="
                  portfolioChartData[`${activePeriod}_${activeMetric}`]?.error
                "
                class="h-80 flex items-center justify-center"
              >
                <div class="text-center">
                  <svg
                    class="h-16 w-16 text-red-400 mx-auto mb-4"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                  </svg>
                  <h3 class="text-lg font-semibold text-red-600 mb-2">
                    Error Loading Data
                  </h3>
                  <p class="text-red-500 text-sm">
                    {{
                      portfolioChartData[`${activePeriod}_${activeMetric}`]
                        .error
                    }}
                  </p>
                </div>
              </div>

              <div v-else class="h-80 flex items-center justify-center">
                <div class="text-center">
                  <svg
                    class="h-16 w-16 text-gray-400 dark:text-gray-600 mx-auto mb-4"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2-2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                    />
                  </svg>
                  <h3
                    class="text-xl font-bold text-gray-900 dark:text-white mb-2"
                  >
                    No Data Available
                  </h3>
                  <p
                    class="text-gray-600 dark:text-gray-400 text-sm max-w-md mx-auto"
                  >
                    Portfolio performance history will appear here once you have
                    cards with price data for the selected time period.
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- End Portfolio Performance Dashboard -->

        <!-- Cards Section -->
        <div
          class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden"
        >
          <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
              <h2
                class="text-lg font-semibold text-gray-900 dark:text-gray-100"
              >
                Cards in Portfolio
              </h2>
              <span
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200"
              >
                {{ totalCards }} total
                {{ totalCards === 1 ? 'card' : 'cards' }}
              </span>
            </div>
          </div>

          <div class="divide-y divide-gray-200 dark:divide-gray-700">
            <!-- Loading State -->
            <div v-if="isLoading" class="p-12 text-center">
              <div
                class="inline-flex items-center justify-center w-16 h-16 bg-indigo-100 dark:bg-indigo-900/30 rounded-full mb-4"
              >
                <svg
                  class="animate-spin h-8 w-8 text-indigo-600 dark:text-indigo-400"
                  fill="none"
                  viewBox="0 0 24 24"
                >
                  <circle
                    class="opacity-25"
                    cx="12"
                    cy="12"
                    r="10"
                    stroke="currentColor"
                    stroke-width="4"
                  />
                  <path
                    class="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                  />
                </svg>
              </div>
              <h3
                class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2"
              >
                Updating portfolio...
              </h3>
              <p class="text-gray-500 dark:text-gray-400">
                Please wait while we refresh your cards
              </p>
            </div>

            <div
              v-else-if="groupedCards && groupedCards.length > 0"
              class="space-y-0"
            >
              <div
                v-for="group in groupedCards"
                :key="group.groupKey"
                class="card-hover hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150 animate-fade-in"
              >
                <div class="p-6">
                  <div class="card-content flex items-start space-x-6">
                    <!-- Card Image - Larger -->
                    <div class="card-image flex-shrink-0">
                      <Link
                        v-if="group.blueprint?.id"
                        :href="route('blueprints.show', group.blueprint.id)"
                        class="block"
                      >
                        <img
                          v-if="group.blueprint?.image_url"
                          :src="group.blueprint.image_url"
                          :alt="group.blueprint.name"
                          class="h-36 w-auto object-contain rounded-lg border-2 border-gray-200 dark:border-gray-600 shadow-sm hover:shadow-md transition-shadow"
                        />
                        <div
                          v-else
                          class="h-36 w-24 bg-gray-200 dark:bg-gray-600 rounded-lg border-2 border-gray-300 dark:border-gray-500 flex items-center justify-center text-sm text-gray-500 dark:text-gray-400 font-medium"
                        >
                          No Image
                        </div>
                      </Link>
                      <div v-else>
                        <img
                          v-if="group.blueprint?.image_url"
                          :src="group.blueprint.image_url"
                          :alt="group.blueprint?.name || 'Blueprint'"
                          class="h-36 w-auto object-contain rounded-lg border-2 border-gray-200 dark:border-gray-600 shadow-sm"
                        />
                      </div>
                    </div>

                    <!-- Card Info - Better Layout -->
                    <div class="flex-1 min-w-0">
                      <div class="flex justify-between items-start">
                        <!-- Left Column - Card Details -->
                        <div class="flex-1 min-w-0 pr-4">
                          <h3 class="text-xl font-bold mb-2">
                            <Link
                              v-if="group.blueprint?.id"
                              :href="route('blueprints.show', group.blueprint.id)"
                              class="text-gray-900 dark:text-gray-100 hover:underline hover:text-blue-600 dark:hover:text-blue-400"
                            >
                              {{ group.blueprint?.name || 'Unknown Card' }}
                            </Link>
                            <span v-else class="text-gray-900 dark:text-gray-100">{{ group.blueprint?.name || 'Unknown Card' }}</span>
                          </h3>

                          <div class="space-y-2">
                            <div class="flex flex-wrap gap-4 text-sm">
                              <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200"
                              >
                                {{ group.condition }}
                              </span>
                              <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200"
                              >
                                {{ group.language }}
                              </span>
                              <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200"
                              >
                                Qty: {{ group.totalQuantity }}
                              </span>
                            </div>

                            <p class="text-sm text-gray-600 dark:text-gray-400">
                              <span class="font-medium">Acquired:</span>
                              {{
                                group.acquired_at
                                  ? new Date(
                                      group.acquired_at
                                    ).toLocaleDateString()
                                  : 'N/A'
                              }}
                            </p>

                            <p
                              v-if="group.blueprint?.expansion"
                              class="text-sm text-gray-500 dark:text-gray-500"
                            >
                              {{ group.blueprint.expansion.name }} ({{
                                group.blueprint.expansion.code
                              }})
                            </p>
                          </div>
                        </div>

                        <!-- Right Column - Pricing Info -->
                        <div class="flex-shrink-0 flex space-x-3">
                          <!-- Single Card Values -->
                          <div
                            class="bg-gray-50 dark:bg-gray-700 p-3 rounded-lg border border-gray-200 dark:border-gray-600 w-36"
                          >
                            <p
                              class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2 text-center"
                            >
                              Per Card
                            </p>
                            <div class="text-center space-y-1">
                              <p
                                class="text-sm font-bold text-gray-900 dark:text-gray-100"
                              >
                                {{ formatCurrency(group.singleEstimatedValue) }}
                              </p>
                              <p
                                class="text-xs text-gray-600 dark:text-gray-400"
                              >
                                Est. Value
                              </p>
                              <div
                                class="border-t border-gray-200 dark:border-gray-600 pt-1 mt-1"
                              >
                                <p
                                  class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                  {{
                                    formatCurrency(group.singlePurchasePrice)
                                  }}
                                </p>
                                <p
                                  class="text-xs text-gray-500 dark:text-gray-500"
                                >
                                  Purchase
                                </p>
                              </div>
                            </div>
                          </div>

                          <!-- Group Total Values -->
                          <div
                            class="bg-gradient-to-br from-indigo-50 to-blue-50 dark:from-indigo-900/30 dark:to-blue-900/30 p-3 rounded-lg border border-indigo-200 dark:border-indigo-700 w-36"
                          >
                            <p
                              class="text-xs font-medium text-indigo-600 dark:text-indigo-400 mb-2 text-center"
                            >
                              Total (×{{ group.totalQuantity }})
                            </p>
                            <div class="text-center space-y-1">
                              <p
                                class="text-sm font-bold text-indigo-900 dark:text-indigo-100"
                              >
                                {{
                                  formatCurrency(
                                    group.estimatedValueCents
                                      ? group.estimatedValueCents / 100
                                      : undefined
                                  )
                                }}
                              </p>
                              <p
                                class="text-xs text-indigo-700 dark:text-indigo-300"
                              >
                                Est. Value
                              </p>
                              <div
                                class="border-t border-indigo-200 dark:border-indigo-600 pt-1 mt-1"
                              >
                                <p
                                  class="text-sm font-bold text-indigo-800 dark:text-indigo-200"
                                >
                                  {{
                                    formatCurrency(
                                      group.purchasePriceCents
                                        ? group.purchasePriceCents / 100
                                        : undefined
                                    )
                                  }}
                                </p>
                                <p
                                  class="text-xs text-indigo-600 dark:text-indigo-400"
                                >
                                  Purchase
                                </p>
                              </div>
                            </div>
                          </div>

                          <!-- Actions -->
                          <div class="flex flex-col gap-2 min-w-0">
                            <button
                              class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors duration-150"
                              @click="editCard(getFirstCardFromGroup(group))"
                            >
                              <svg
                                class="h-4 w-4 mr-1.5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                              >
                                <path
                                  stroke-linecap="round"
                                  stroke-linejoin="round"
                                  stroke-width="2"
                                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                                />
                              </svg>
                              Edit
                            </button>
                            <button
                              class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-red-700 dark:text-red-400 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors duration-150"
                              @click="openRemoveCardModal(group)"
                            >
                              <svg
                                class="h-4 w-4 mr-1.5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                              >
                                <path
                                  stroke-linecap="round"
                                  stroke-linejoin="round"
                                  stroke-width="2"
                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                />
                              </svg>
                              Remove
                            </button>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="p-12 text-center">
              <div
                class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 dark:bg-gray-700 rounded-full mb-4"
              >
                <svg
                  class="h-8 w-8 text-gray-400 dark:text-gray-500"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="1.5"
                    d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                  />
                </svg>
              </div>
              <h3
                class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2"
              >
                No cards in this portfolio yet
              </h3>
              <p class="text-gray-500 dark:text-gray-400 mb-6">
                Start building your collection by adding cards to this
                portfolio.
              </p>
              <button
                class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg shadow-sm transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                @click="openAddCardModal"
              >
                <svg
                  class="h-5 w-5 mr-2"
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
                Add Your First Card
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <AddCardModal
      v-if="showAddCardModal"
      :portfolio-id="portfolio.id"
      :portfolio-name="portfolio.name"
      @close="closeAddCardModal"
      @card-added="handleCardAddedToCurrentPortfolio"
    />

    <EditCardModal
      v-if="showEditCardModal && cardToEdit"
      :user-card="cardToEdit"
      :portfolio-id="portfolio.id"
      @close="closeEditCardModal"
      @card-updated="handleCardUpdated"
    />

    <RemoveCardModal
      :show="showRemoveCardModal"
      :card-group="cardGroupToRemove"
      @close="closeRemoveCardModal"
      @confirm="confirmRemoveCards"
    />
  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AddCardModal from '@/Components/AddCardModal.vue';
import EditCardModal from '@/Components/EditCardModal.vue';
import RemoveCardModal from '@/Components/RemoveCardModal.vue';
import PriceHistoryChart from '@/Components/PriceHistoryChart.vue';
import TooltipHelper from '@/Components/TooltipHelper.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { ref, computed, onMounted, reactive } from 'vue';
import {
  usePortfolioStore,
  Portfolio as PortfolioInterface,
  UserCard as UserCardInterface,
  Blueprint as BlueprintInterface,
} from '@/stores/portfolioStore';
import { useCurrency } from '@/composables/useCurrency';
import { useToast } from '@/composables/useToast';
import axios from 'axios';

type UserCardData = UserCardInterface;

interface PortfolioPageData extends PortfolioInterface {
  user_cards?: UserCardData[];
}

interface CardGroup {
  groupKey: string;
  blueprint?: BlueprintInterface;
  condition: string;
  language: string;
  totalQuantity: number;
  estimatedValueCents: number;
  purchasePriceCents: number;
  acquired_at?: string;
  singleEstimatedValue: number;
  singlePurchasePrice: number;
}

const props = defineProps<{
  portfolio: PortfolioPageData;
}>();

const portfolioStore = usePortfolioStore();
const { formatCurrency } = useCurrency();
const { success: showSuccessToast, error: showErrorToast } = useToast();

const showAddCardModal = ref(false);
const showEditCardModal = ref(false);
const showRemoveCardModal = ref(false);
const cardToEdit = ref<UserCardData | null>(null);
const cardGroupToRemove = ref<CardGroup | null>(null);
const isLoading = ref(false);

// Portfolio value chart state
const portfolioChartData = reactive<Record<string, any>>({});
const activePeriod = ref('1M'); // Default period
const activeMetric = ref('market_value'); // Default metric
const timePeriods = ['1D', '1W', '1M', '3M', '1Y', 'MAX'];
const metrics = [
  {
    key: 'market_value',
    label: 'Market Value',
    color: 'rgba(34, 197, 94, 1)',
    bgColor: 'rgba(34, 197, 94, 0.1)',
  },
  {
    key: 'cost_basis',
    label: 'Cost Basis',
    color: 'rgba(59, 130, 246, 1)',
    bgColor: 'rgba(59, 130, 246, 0.1)',
  },
  {
    key: 'performance',
    label: 'Performance %',
    color: 'rgba(168, 85, 247, 1)',
    bgColor: 'rgba(168, 85, 247, 0.1)',
  },
];
const isLoadingChart = ref(false);

const openAddCardModal = () => {
  showAddCardModal.value = true;
};

const closeAddCardModal = () => {
  showAddCardModal.value = false;
};

const handleCardAddedToCurrentPortfolio = () => {
  // Quando una carta è aggiunta al portfolio corrente, la prop `portfolio` non si aggiorna automaticamente.
  // Dobbiamo ricaricare i dati per questa pagina.
  isLoading.value = true;
  router.reload({
    only: ['portfolio'],
    onFinish: () => {
      isLoading.value = false;
    },
  });
  // In alternativa, potremmo aggiornare manualmente lo store e sperare che la prop si aggiorni,
  // ma router.reload() è più diretto per le props da Inertia.
};

const editCard = (card: UserCardData) => {
  cardToEdit.value = card;
  showEditCardModal.value = true;
};

const closeEditCardModal = () => {
  showEditCardModal.value = false;
  cardToEdit.value = null;
};

const handleCardUpdated = () => {
  closeEditCardModal();
  isLoading.value = true;
  router.reload({
    only: ['portfolio'],
    onFinish: () => {
      isLoading.value = false;
    },
  });
};

const openRemoveCardModal = (group: CardGroup) => {
  cardGroupToRemove.value = group;
  showRemoveCardModal.value = true;
};

const closeRemoveCardModal = () => {
  showRemoveCardModal.value = false;
  cardGroupToRemove.value = null;
};

const confirmRemoveCards = async (quantityToRemove: number) => {
  if (!cardGroupToRemove.value) {
    closeRemoveCardModal();
    return;
  }

  try {
    // Find all cards in this group and remove the specified quantity
    const cardsInGroup =
      props.portfolio.user_cards?.filter(
        (card) =>
          card.blueprint?.name === cardGroupToRemove.value?.blueprint?.name &&
          card.condition === cardGroupToRemove.value?.condition &&
          card.language === cardGroupToRemove.value?.language
      ) || [];

    // Remove cards one by one until we reach the desired quantity
    let remainingToRemove = quantityToRemove;

    for (const card of cardsInGroup) {
      if (remainingToRemove <= 0) break;

      const cardQuantity = card.pivot?.quantity || 1;
      const quantityToRemoveFromThisCard = Math.min(
        remainingToRemove,
        cardQuantity
      );

      // If we're removing all cards from this specific card entry, remove the entire entry
      if (quantityToRemoveFromThisCard === cardQuantity) {
        await portfolioStore.removeUserCardFromPortfolio(
          props.portfolio.id,
          card.id
        );
      } else {
        // Update the quantity for this card
        await portfolioStore.updateUserCardQuantityInPortfolio(
          props.portfolio.id,
          card.id,
          cardQuantity - quantityToRemoveFromThisCard
        );
      }

      remainingToRemove -= quantityToRemoveFromThisCard;
    }

    showSuccessToast(
      'Cards removed successfully!',
      `${quantityToRemove} ${quantityToRemove === 1 ? 'copy' : 'copies'} of ${cardGroupToRemove.value.blueprint?.name} ${quantityToRemove === 1 ? 'has' : 'have'} been removed from "${props.portfolio.name}"`
    );

    closeRemoveCardModal();
    isLoading.value = true;
    router.reload({
      only: ['portfolio'],
      onFinish: () => {
        isLoading.value = false;
      },
    });
  } catch (error) {
    console.error('Error removing cards from portfolio:', error);
    showErrorToast(
      'Error removing cards',
      'Failed to remove cards from portfolio. Please try again.'
    );
    closeRemoveCardModal();
  }
};

const getFirstCardFromGroup = (group: CardGroup): UserCardData => {
  // Find the first card that matches this group
  const card = props.portfolio.user_cards?.find(
    (c) =>
      c.blueprint?.name === group.blueprint?.name &&
      c.condition === group.condition &&
      c.language === group.language &&
      c.purchase_price_cents ===
        (group.singlePurchasePrice
          ? Math.round(group.singlePurchasePrice * 100)
          : null)
  );

  if (!card) {
    // Fallback: take the first card with matching name/condition/language
    const fallbackCard = props.portfolio.user_cards?.find(
      (c) =>
        c.blueprint?.name === group.blueprint?.name &&
        c.condition === group.condition &&
        c.language === group.language
    );

    if (!fallbackCard) {
      throw new Error('Card not found in group');
    }

    return fallbackCard;
  }

  // Create a representative card with the correct total quantity
  const representativeCard: UserCardData = {
    ...card,
    pivot: {
      ...card.pivot,
      quantity: group.totalQuantity, // Use the total quantity from the group
    },
  };

  return representativeCard;
};

// Portfolio value history functions
const fetchPortfolioValueHistory = async (
  period: string = '1M',
  metric: string = 'market_value'
) => {
  const cacheKey = `${period}_${metric}`;

  // Only prevent re-fetching if we have data for this specific period+metric combination
  if (
    portfolioChartData[cacheKey]?.data &&
    activePeriod.value === period &&
    activeMetric.value === metric
  ) {
    return;
  }

  // Set loading state for this period+metric combination
  portfolioChartData[cacheKey] = { isLoading: true, data: null, error: null };
  activePeriod.value = period;
  activeMetric.value = metric;
  isLoadingChart.value = true;

  try {
    const response = await axios.get(
      route('api.portfolios.value-history', {
        portfolio: props.portfolio.id,
        period: period,
        metric: metric,
      })
    );
    const history = response.data;

    if (history.length === 0) {
      portfolioChartData[cacheKey].data = null;
    } else {
      const currentMetric = metrics.find((m) => m.key === metric);
      const labels = history.map((p: any) => formatDateShort(p.fetched_at));

      // Handle different data formats based on metric
      let data, label, yAxisCallback;

      if (metric === 'performance') {
        data = history.map((p: any) => p.performance_percentage);
        label = 'Performance (%)';
        yAxisCallback = (value: any) => value.toFixed(1) + '%';
      } else if (metric === 'cost_basis') {
        data = history.map((p: any) => p.cost_basis_cents / 100);
        label = 'Cost Basis (EUR)';
        yAxisCallback = (value: any) => '€' + value.toFixed(0);
      } else {
        data = history.map((p: any) => p.market_value_cents / 100);
        label = 'Market Value (EUR)';
        yAxisCallback = (value: any) => {
          if (value >= 1000) {
            return '€' + (value / 1000).toFixed(1) + 'K';
          }
          return '€' + value.toFixed(0);
        };
      }

      portfolioChartData[cacheKey].data = {
        labels,
        datasets: [
          {
            label: label,
            backgroundColor: currentMetric?.bgColor || 'rgba(34, 197, 94, 0.1)',
            borderColor: currentMetric?.color || 'rgba(34, 197, 94, 1)',
            data,
            tension: 0.1,
            fill: true,
            borderWidth: 2,
            pointRadius: 0,
            pointHoverRadius: 6,
            pointHoverBorderWidth: 2,
            pointHoverBorderColor:
              currentMetric?.color || 'rgba(34, 197, 94, 1)',
            pointHoverBackgroundColor: 'white',
          },
        ],
        yAxisCallback, // Store the callback for use in chart options
      };
    }
  } catch (err) {
    console.error(
      `Failed to fetch portfolio value history for ${period} (${metric}):`,
      err
    );
    portfolioChartData[cacheKey].error = 'Failed to load chart data.';
  } finally {
    portfolioChartData[cacheKey].isLoading = false;
    isLoadingChart.value = false;
  }
};

const formatDateShort = (dateString: string) => {
  if (!dateString) return 'N/A';
  return new Date(dateString).toLocaleDateString('it-IT', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  });
};

// formatCurrency now imported from useCurrency() composable

const totalEstimatedValue = computed(() => {
  return (
    props.portfolio.user_cards?.reduce((sum, card) => {
      const value = card.estimated_value_cents
        ? card.estimated_value_cents / 100
        : 0;
      return sum + value * (card.pivot?.quantity || 1);
    }, 0) || 0
  );
});

// Total number of cards (sum of quantities)
const totalCards = computed(() => {
  return (
    props.portfolio.user_cards?.reduce((sum, card) => {
      return sum + (card.pivot?.quantity || 1);
    }, 0) || 0
  );
});

const totalPurchaseCost = computed(() => {
  return (
    props.portfolio.user_cards?.reduce((sum, card) => {
      const cost = card.purchase_price_cents
        ? card.purchase_price_cents / 100
        : 0;
      return sum + cost * (card.pivot?.quantity || 1);
    }, 0) || 0
  );
});

const profitLoss = computed(() => {
  return totalEstimatedValue.value - totalPurchaseCost.value;
});

const groupedCards = computed(() => {
  const cards = props.portfolio.user_cards || [];
  const grouped: Record<string, CardGroup> = {};

  cards.forEach((card) => {
    const groupKey = `${card.blueprint?.name}-${card.condition}-${card.language}-${card.purchase_price_cents}`;
    if (!grouped[groupKey]) {
      grouped[groupKey] = {
        groupKey,
        blueprint: card.blueprint,
        condition: card.condition,
        language: card.language,
        totalQuantity: 0,
        estimatedValueCents: 0,
        purchasePriceCents: 0,
        acquired_at: card.acquired_at,
        singleEstimatedValue: 0,
        singlePurchasePrice: 0,
      };
    }
    grouped[groupKey].totalQuantity += card.pivot.quantity;

    // Calculate total values (price per card × quantity)
    grouped[groupKey].estimatedValueCents +=
      (card.estimated_value_cents || 0) * card.pivot.quantity;
    grouped[groupKey].purchasePriceCents +=
      (card.purchase_price_cents || 0) * card.pivot.quantity;

    // Calculate single card values (these remain the same per card)
    grouped[groupKey].singleEstimatedValue = card.estimated_value_cents
      ? card.estimated_value_cents / 100
      : 0;
    grouped[groupKey].singlePurchasePrice = card.purchase_price_cents
      ? card.purchase_price_cents / 100
      : 0;
  });

  return Object.values(grouped);
});

// Portfolio value statistics
const portfolioStatistics = computed(() => {
  const cacheKey = `${activePeriod.value}_${activeMetric.value}`;
  const historyData = portfolioChartData[cacheKey]?.data?.datasets[0]?.data;

  if (!historyData || historyData.length < 1) {
    // Fallback to current values based on active metric
    if (activeMetric.value === 'performance') {
      const gain = totalEstimatedValue.value - totalPurchaseCost.value;
      const percentage =
        totalPurchaseCost.value > 0
          ? (gain / totalPurchaseCost.value) * 100
          : 0;
      return {
        currentValue: `${percentage.toFixed(1)}%`,
        change: 'N/A',
        percentage: 'N/A',
        positive: percentage >= 0,
      };
    } else if (activeMetric.value === 'cost_basis') {
      return {
        currentValue: formatCurrency(totalPurchaseCost.value),
        change: 'N/A',
        percentage: 'N/A',
        positive: true,
      };
    } else {
      return {
        currentValue: formatCurrency(totalEstimatedValue.value),
        change: 'N/A',
        percentage: 'N/A',
        positive: true,
      };
    }
  }

  const currentValue = historyData[historyData.length - 1];
  const previousValue = historyData[0];

  const change = currentValue - previousValue;
  const percentage = previousValue !== 0 ? (change / previousValue) * 100 : 0;

  // Format values based on metric type
  if (activeMetric.value === 'performance') {
    return {
      currentValue: `${currentValue.toFixed(1)}%`,
      change: `${Math.abs(change).toFixed(1)}%`,
      percentage: `${percentage.toFixed(1)}%`,
      positive: change >= 0,
    };
  } else {
    return {
      currentValue: formatCurrency(currentValue),
      change: formatCurrency(Math.abs(change)),
      percentage: `${percentage.toFixed(1)}%`,
      positive: change >= 0,
    };
  }
});

// Overall portfolio statistics for header
const overallStatistics = computed(() => {
  const marketValue = totalEstimatedValue.value;
  const costBasis = totalPurchaseCost.value;
  const unrealizedGain = marketValue - costBasis;
  const performance = costBasis > 0 ? (unrealizedGain / costBasis) * 100 : 0;

  return {
    marketValue: formatCurrency(marketValue),
    costBasis: formatCurrency(costBasis),
    unrealizedGain: formatCurrency(Math.abs(unrealizedGain)),
    unrealizedGainPositive: unrealizedGain >= 0,
    performance: `${performance.toFixed(1)}%`,
    performancePositive: performance >= 0,
  };
});

// Initialize chart on mount
onMounted(() => {
  if (props.portfolio.user_cards && props.portfolio.user_cards.length > 0) {
    fetchPortfolioValueHistory(activePeriod.value, activeMetric.value);
  }
});
</script>

<style scoped>
/* Smooth transitions for hover effects */
.hover\:bg-gray-50:hover {
  transition: background-color 0.15s ease-in-out;
}

/* Custom animations */
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.animate-fade-in {
  animation: fadeIn 0.3s ease-out;
}

/* Focus styles for better accessibility */
button:focus {
  outline: 2px solid transparent;
  outline-offset: 2px;
}

/* Improved card hover effect */
.card-hover:hover {
  transform: translateY(-1px);
  box-shadow:
    0 10px 25px -5px rgba(0, 0, 0, 0.1),
    0 4px 6px -2px rgba(0, 0, 0, 0.05);
  transition: all 0.15s ease-in-out;
}

/* Responsive improvements */
@media (max-width: 640px) {
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
  }

  .card-content {
    flex-direction: column;
    space-y: 1rem;
  }

  .card-image {
    align-self: center;
    margin-bottom: 1rem;
  }
}
</style>
