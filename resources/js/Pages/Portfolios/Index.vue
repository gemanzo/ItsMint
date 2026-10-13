<template>
  <AuthenticatedLayout>
    <Head title="My Portfolios" />

    <!-- Enhanced Header with Stats -->
    <div
      class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700"
    >
      <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-4 sm:py-6">
        <div
          class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-3 sm:space-y-0"
        >
          <div>
            <h1
              class="text-xl sm:text-2xl font-semibold text-gray-900 dark:text-white"
            >
              Portfolios
            </h1>
            <p class="text-gray-500 dark:text-gray-400 mt-1 text-xs sm:text-sm">
              {{ portfolios.length }}
              {{ portfolios.length === 1 ? 'portfolio' : 'portfolios' }} •
              {{ totalCards }} total cards •
              {{ formatCurrency(totalValue) }} total value
            </p>
          </div>
          <div
            class="flex flex-col sm:flex-row items-stretch sm:items-center space-y-2 sm:space-y-0 sm:space-x-2"
          >
            <div
              class="flex items-center bg-gray-100 dark:bg-gray-700 rounded-button p-0.5"
            >
              <button
                :class="
                  viewMode === 'grid'
                    ? 'bg-white dark:bg-gray-600 shadow-sm'
                    : ''
                "
                class="p-2 rounded-button transition-all duration-150 flex-1 sm:flex-none"
                aria-label="Grid view"
                @click="viewMode = 'grid'"
              >
                <svg
                  class="h-4 w-4 text-gray-600 dark:text-gray-300 mx-auto"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"
                  />
                </svg>
              </button>
              <button
                :class="
                  viewMode === 'list'
                    ? 'bg-white dark:bg-gray-600 shadow-sm'
                    : ''
                "
                class="p-2 rounded-button transition-all duration-150 flex-1 sm:flex-none"
                aria-label="List view"
                @click="viewMode = 'list'"
              >
                <svg
                  class="h-4 w-4 text-gray-600 dark:text-gray-300 mx-auto"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M4 6h16M4 10h16M4 14h16M4 18h16"
                  />
                </svg>
              </button>
            </div>
            <!-- Create Portfolio Button with Premium Check -->
            <div class="relative group">
              <button
                :disabled="!canCreatePortfolio"
                :class="[
                  'px-3 sm:px-4 py-2 font-medium rounded-button transition-colors flex items-center justify-center space-x-2 text-sm',
                  canCreatePortfolio
                    ? 'bg-gray-600 hover:bg-gray-700 text-white cursor-pointer'
                    : 'bg-gray-300 dark:bg-gray-600 text-gray-500 dark:text-gray-400 cursor-not-allowed'
                ]"
                :title="canCreatePortfolio ? 'Create new portfolio' : 'Upgrade to Premium to create multiple portfolios'"
                @click="canCreatePortfolio ? showCreatePortfolioModal = true : null"
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
                    d="M12 6v6m0 0v6m0-6h6m-6 0H6"
                  />
                </svg>
                <span class="hidden sm:inline">Create Portfolio</span>
                <span class="sm:hidden">Create</span>
                <PremiumBadge
                  v-if="!canCreatePortfolio"
                  variant="subtle"
                  size="sm"
                  :show-icon="false"
                  text="Premium"
                  class="ml-1"
                />
              </button>
              <!-- Tooltip for disabled state -->
              <div
                v-if="!canCreatePortfolio"
                class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 text-xs text-white bg-gray-900 dark:bg-gray-700 rounded-lg shadow-lg whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10"
              >
                Free plan allows only 1 portfolio. Upgrade to Premium for unlimited portfolios.
                <div class="absolute top-full left-1/2 transform -translate-x-1/2 border-4 border-transparent border-t-gray-900 dark:border-t-gray-700"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Quick Stats Bar -->
        <div
          v-if="portfolios.length > 0"
          class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-3"
        >
          <div
            class="bg-white dark:bg-gray-800 rounded-card p-3 sm:p-4 border border-gray-200 dark:border-gray-700 shadow-card"
          >
            <p class="text-gray-600 dark:text-gray-400 text-xs font-medium">
              Total Portfolios
            </p>
            <p
              class="text-lg sm:text-xl font-bold text-gray-900 dark:text-white"
            >
              {{ portfolios.length }}
            </p>
          </div>
          <div
            class="bg-white dark:bg-gray-800 rounded-card p-3 sm:p-4 border border-gray-200 dark:border-gray-700 shadow-card"
          >
            <p class="text-gray-600 dark:text-gray-400 text-xs font-medium">
              Total Cards
            </p>
            <p
              class="text-lg sm:text-xl font-bold text-gray-900 dark:text-white"
            >
              {{ totalCards }}
            </p>
          </div>
          <div
            class="bg-white dark:bg-gray-800 rounded-card p-3 sm:p-4 border border-gray-200 dark:border-gray-700 shadow-card"
          >
            <p class="text-gray-600 dark:text-gray-400 text-xs font-medium">
              Avg per Portfolio
            </p>
            <p
              class="text-lg sm:text-xl font-bold text-gray-900 dark:text-white"
            >
              {{ Math.round(totalCards / portfolios.length) }}
            </p>
          </div>
          <div
            class="bg-white dark:bg-gray-800 rounded-card p-3 sm:p-4 border border-gray-200 dark:border-gray-700 shadow-card"
          >
            <p class="text-gray-600 dark:text-gray-400 text-xs font-medium">
              Total Value
            </p>
            <p
              class="text-lg sm:text-xl font-bold text-gray-900 dark:text-white"
            >
              {{ formatCurrency(totalValue) }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content Wrapper -->
    <div class="min-h-screen bg-gray-50 dark:bg-gray-900 py-4 sm:py-8">
      <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
        <!-- Search and Filters -->
        <div v-if="portfolios.length > 0" class="mb-4 sm:mb-6">
          <div
            class="bg-white dark:bg-gray-800 rounded-card p-3 sm:p-4 shadow-card border border-gray-200 dark:border-gray-700"
          >
            <div
              class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-3 sm:space-y-0 sm:space-x-4"
            >
              <div class="relative flex-1 max-w-md">
                <svg
                  class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400"
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
                <input
                  v-model="searchQuery"
                  type="text"
                  placeholder="Search portfolios..."
                  class="w-full px-4 py-3 pl-10 pr-4 text-sm border border-gray-300 dark:border-gray-600 rounded-card bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-gray-500 focus:border-gray-500 transition-colors"
                />
              </div>
              <div class="flex items-center space-x-3">
                <select
                  v-model="sortBy"
                  class="w-full sm:w-auto px-3 py-3 text-sm border border-gray-300 dark:border-gray-600 rounded-button bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-gray-500 transition-colors"
                >
                  <option value="name">Sort by Name</option>
                  <option value="cards">Sort by Card Count</option>
                  <option value="value">Sort by Value</option>
                  <option value="created">Sort by Date Created</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- Portfolios Grid/List -->
        <div v-if="filteredPortfolios.length > 0">
          <!-- Grid View -->
          <div
            v-if="viewMode === 'grid'"
            class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4"
          >
            <div
              v-for="portfolio in filteredPortfolios"
              :key="portfolio.id"
              class="bg-white dark:bg-gray-800 rounded-card p-4 sm:p-6 shadow-card border border-gray-200 dark:border-gray-700 hover:shadow-card-hover transition-all duration-200 group"
            >
              <!-- Portfolio Header -->
              <div class="flex items-start justify-between mb-3 sm:mb-4">
                <div class="flex items-center space-x-3 flex-1 min-w-0">
                  <div
                    class="w-10 h-10 sm:w-12 sm:h-12 bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg flex items-center justify-center flex-shrink-0"
                  >
                    <span class="text-white font-bold text-sm sm:text-base">
                      {{ portfolio.name.charAt(0).toUpperCase() }}
                    </span>
                  </div>
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center space-x-2 flex-wrap">
                      <Link
                        :href="route('portfolios.show', portfolio.id)"
                        class="text-base sm:text-lg font-semibold text-gray-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400 transition-colors block truncate"
                      >
                        {{ portfolio.name }}
                      </Link>
                      <PremiumBadge
                        v-if="portfolio.is_locked"
                        :show="portfolio.is_locked && !isPremium"
                        variant="default"
                        size="sm"
                      />
                    </div>
                    <p
                      class="text-xs sm:text-sm text-gray-500 dark:text-gray-400"
                    >
                      Created {{ formatDate(portfolio.created_at) }}
                    </p>
                  </div>
                </div>
                <div class="flex items-center space-x-1 sm:space-x-2">
                  <button
                    :disabled="portfolio.is_locked && !isPremium"
                    :class="[
                      'p-2 rounded-lg transition-colors',
                      portfolio.is_locked && !isPremium
                        ? 'text-gray-300 dark:text-gray-600 cursor-not-allowed'
                        : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700'
                    ]"
                    aria-label="Edit portfolio"
                    :title="portfolio.is_locked && !isPremium ? 'Premium feature - upgrade to unlock' : 'Edit portfolio'"
                    @click="portfolio.is_locked && !isPremium ? null : editPortfolio(portfolio)"
                  >
                    <svg
                      class="w-4 h-4"
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
                  </button>
                  <button
                    :disabled="portfolio.is_locked && !isPremium"
                    :class="[
                      'p-2 rounded-lg transition-colors',
                      portfolio.is_locked && !isPremium
                        ? 'text-gray-300 dark:text-gray-600 cursor-not-allowed'
                        : 'text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20'
                    ]"
                    aria-label="Delete portfolio"
                    :title="portfolio.is_locked && !isPremium ? 'Premium feature - upgrade to unlock' : 'Delete portfolio'"
                    @click="portfolio.is_locked && !isPremium ? null : deletePortfolio(portfolio)"
                  >
                    <svg
                      class="w-4 h-4"
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
                  </button>
                </div>
              </div>

              <!-- Portfolio Stats -->
              <div class="grid grid-cols-2 gap-3 sm:gap-4 mb-4">
                <div
                  class="text-center p-2 sm:p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg"
                >
                  <p
                    class="text-xs text-gray-600 dark:text-gray-400 font-medium"
                  >
                    Cards
                  </p>
                  <p
                    class="text-lg sm:text-xl font-bold text-gray-900 dark:text-white"
                  >
                    {{ getPortfolioTotalCards(portfolio) }}
                  </p>
                </div>
                <div
                  class="text-center p-2 sm:p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg"
                >
                  <p
                    class="text-xs text-gray-600 dark:text-gray-400 font-medium"
                  >
                    Value
                  </p>
                  <p
                    class="text-lg sm:text-xl font-bold text-gray-900 dark:text-white"
                  >
                    {{ formatCurrency(portfolio.total_value_cents / 100 || 0) }}
                  </p>
                </div>
              </div>

              <!-- Portfolio Actions -->
              <div
                class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-2"
              >
                <Link
                  :href="route('portfolios.show', portfolio.id)"
                  class="flex-1 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-button transition-colors text-center"
                >
                  View Details
                </Link>
                <button
                  :disabled="portfolio.is_locked && !isPremium"
                  :class="[
                    'px-3 py-2 text-sm font-medium rounded-button transition-colors',
                    portfolio.is_locked && !isPremium
                      ? 'bg-gray-100 dark:bg-gray-700 text-gray-400 dark:text-gray-600 cursor-not-allowed'
                      : 'bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200'
                  ]"
                  :title="portfolio.is_locked && !isPremium ? 'Premium feature - upgrade to unlock' : 'Add Cards'"
                  @click="portfolio.is_locked && !isPremium ? null : openAddCardModal(portfolio)"
                >
                  Add Cards
                </button>
              </div>
            </div>
          </div>

          <!-- List View -->
          <div
            v-else
            class="bg-white dark:bg-gray-800 rounded-card border border-gray-200 dark:border-gray-700 overflow-hidden"
          >
            <div class="divide-y divide-gray-200 dark:divide-gray-700">
              <div
                v-for="portfolio in filteredPortfolios"
                :key="portfolio.id"
                class="p-3 sm:p-6 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
              >
                <div
                  class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-3 sm:space-y-0"
                >
                  <div
                    class="flex items-center space-x-3 sm:space-x-4 flex-1 min-w-0"
                  >
                    <div
                      class="w-10 h-10 sm:w-12 sm:h-12 bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg flex items-center justify-center flex-shrink-0"
                    >
                      <span class="text-white font-bold text-sm sm:text-base">
                        {{ portfolio.name.charAt(0).toUpperCase() }}
                      </span>
                    </div>
                    <div class="flex-1 min-w-0">
                      <div class="flex items-center space-x-2 flex-wrap">
                        <Link
                          :href="route('portfolios.show', portfolio.id)"
                          class="text-base sm:text-lg font-semibold text-gray-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400 transition-colors block truncate"
                        >
                          {{ portfolio.name }}
                        </Link>
                        <PremiumBadge
                          v-if="portfolio.is_locked"
                          :show="portfolio.is_locked && !isPremium"
                          variant="default"
                          size="sm"
                        />
                      </div>
                      <div
                        class="flex flex-wrap items-center space-x-4 text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1"
                      >
                        <span>{{ getPortfolioTotalCards(portfolio) }} cards</span>
                        <span>{{
                          formatCurrency(portfolio.total_value || 0)
                        }}</span>
                        <span
                          >Created {{ formatDate(portfolio.created_at) }}</span
                        >
                      </div>
                    </div>
                  </div>

                  <div class="flex items-center space-x-2 sm:space-x-3">
                    <Link
                      :href="route('portfolios.show', portfolio.id)"
                      class="px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-button transition-colors"
                    >
                      View
                    </Link>
                    <button
                      :disabled="portfolio.is_locked && !isPremium"
                      :class="[
                        'px-3 py-2 text-sm font-medium rounded-button transition-colors',
                        portfolio.is_locked && !isPremium
                          ? 'bg-gray-100 dark:bg-gray-700 text-gray-400 dark:text-gray-600 cursor-not-allowed'
                          : 'bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200'
                      ]"
                      :title="portfolio.is_locked && !isPremium ? 'Premium feature - upgrade to unlock' : 'Add Cards'"
                      @click="portfolio.is_locked && !isPremium ? null : openAddCardModal(portfolio)"
                    >
                      Add Cards
                    </button>
                    <button
                      :disabled="portfolio.is_locked && !isPremium"
                      :class="[
                        'p-2 rounded-lg transition-colors',
                        portfolio.is_locked && !isPremium
                          ? 'text-gray-300 dark:text-gray-600 cursor-not-allowed'
                          : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700'
                      ]"
                      aria-label="Edit portfolio"
                      :title="portfolio.is_locked && !isPremium ? 'Premium feature - upgrade to unlock' : 'Edit portfolio'"
                      @click="portfolio.is_locked && !isPremium ? null : editPortfolio(portfolio)"
                    >
                      <svg
                        class="w-4 h-4"
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
                    </button>
                    <button
                      :disabled="portfolio.is_locked && !isPremium"
                      :class="[
                        'p-2 rounded-lg transition-colors',
                        portfolio.is_locked && !isPremium
                          ? 'text-gray-300 dark:text-gray-600 cursor-not-allowed'
                          : 'text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20'
                      ]"
                      aria-label="Delete portfolio"
                      :title="portfolio.is_locked && !isPremium ? 'Premium feature - upgrade to unlock' : 'Delete portfolio'"
                      @click="portfolio.is_locked && !isPremium ? null : deletePortfolio(portfolio)"
                    >
                      <svg
                        class="w-4 h-4"
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
                    </button>
                  </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div
          v-if="portfolios.length === 0"
          class="text-center py-8 sm:py-12"
        >
          <div
            class="bg-white dark:bg-gray-800 rounded-card p-6 sm:p-8 border border-gray-200 dark:border-gray-700"
          >
            <svg
              class="mx-auto h-12 w-12 sm:h-16 sm:w-16 text-gray-400"
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
              class="mt-2 text-lg sm:text-xl font-medium text-gray-900 dark:text-white"
            >
              No portfolios yet
            </h3>
            <p
              class="mt-1 text-sm sm:text-base text-gray-500 dark:text-gray-400"
            >
              Get started by creating your first portfolio to organize your card
              collection.
            </p>
            <div class="mt-6">
              <button
                type="button"
                :disabled="!canCreatePortfolio"
                :class="[
                  'inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 transition-colors',
                  canCreatePortfolio
                    ? 'text-white bg-blue-600 hover:bg-blue-700 focus:ring-blue-500'
                    : 'text-gray-400 bg-gray-300 dark:bg-gray-600 cursor-not-allowed'
                ]"
                :title="canCreatePortfolio ? 'Create your first portfolio' : 'Upgrade to Premium to create multiple portfolios'"
                @click="canCreatePortfolio ? showCreatePortfolioModal = true : null"
              >
                <svg
                  class="-ml-1 mr-2 h-5 w-5"
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
                <PremiumBadge
                  v-if="!canCreatePortfolio"
                  variant="subtle"
                  size="sm"
                  :show-icon="false"
                  text="Premium"
                  class="ml-2"
                />
              </button>
              <!-- Info message for free plan users -->
              <p
                v-if="!canCreatePortfolio"
                class="mt-3 text-xs text-gray-500 dark:text-gray-400"
              >
                Free plan allows only 1 portfolio.
                <a
                  href="/subscribe"
                  class="text-blue-600 dark:text-blue-400 hover:text-blue-500 dark:hover:text-blue-300 font-medium underline"
                >
                  Upgrade to Premium
                </a>
                for unlimited portfolios.
              </p>
            </div>
          </div>
        </div>

        <!-- No Search Results -->
        <div v-else class="text-center py-8 sm:py-12">
          <div
            class="bg-white dark:bg-gray-800 rounded-card p-6 sm:p-8 border border-gray-200 dark:border-gray-700"
          >
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
                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
              />
            </svg>
            <h3
              class="mt-2 text-base sm:text-lg font-medium text-gray-900 dark:text-white"
            >
              No portfolios found
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
              Try adjusting your search or filters to find what you're looking
              for.
            </p>
            <div class="mt-4">
              <button
                type="button"
                class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-500 dark:hover:text-blue-300 font-medium"
                @click="searchQuery = ''"
              >
                Clear search
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
    </div>  
    <!-- End main content wrapper -->

    <!-- Modals -->
    <CreatePortfolioModal
      :is-open="showCreatePortfolioModal"
      @close="closeCreatePortfolioModal"
      @portfolio-created="handlePortfolioCreated"
    />

    <EditPortfolioModal
      v-if="portfolioToEdit"
      :show="showEditPortfolioModal"
      :portfolio="portfolioToEdit"
      @close="closeEditPortfolioModal"
      @portfolio-updated="handlePortfolioUpdated"
    />

    <DeletePortfolioModal
      v-if="portfolioToDelete"
      :show="showDeletePortfolioModal"
      :portfolio-name="portfolioToDelete.name"
      :portfolio-id="portfolioToDelete.id"
      @close="closeDeletePortfolioModal"
      @deleted="handlePortfolioDeleted"
    />

    <AddCardModal
      v-if="targetPortfolio"
      :show="showAddCardModal"
      :portfolio-id="targetPortfolio.id"
      @close="closeAddCardModal"
      @card-added="handleCardAdded"
    />
  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import CreatePortfolioModal from '@/Components/CreatePortfolioModal.vue';
import EditPortfolioModal from '@/Components/EditPortfolioModal.vue';
import DeletePortfolioModal from '@/Components/DeletePortfolioModal.vue';
import AddCardModal from '@/Components/AddCardModal.vue';
import PremiumBadge from '@/Components/PremiumBadge.vue';
import { Link } from '@inertiajs/vue3';
import { usePortfolioStore } from '@/stores/portfolioStore';
import { useToast } from '@/composables/useToast';
import { useFeatures } from '@/composables/useFeatures';

interface PortfolioData {
  id: number;
  name: string;
  description?: string;
  user_cards: UserCard[];
  user_cards_count: number;
  created_at: string;
  total_value_cents: number;
  cards_count?: number;
  total_value?: number;
  is_locked?: boolean;
}

interface UserCard {
  id: number;
  pivot: {
    quantity: number;
  };
}

const props = defineProps<{
  portfolios: PortfolioData[];
}>();

const portfolioStore = usePortfolioStore();
const { success, error } = useToast();
const { isPremium } = useFeatures();

// Check if user can create new portfolio
const canCreatePortfolio = computed(() => {
  return isPremium.value || props.portfolios.length === 0;
});

// Reactive variables
const showCreatePortfolioModal = ref(false);
const showEditPortfolioModal = ref(false);
const portfolioToEdit = ref<PortfolioData | null>(null);
const viewMode = ref<'grid' | 'list'>('grid');
const searchQuery = ref('');
const sortBy = ref('name');
const showDeletePortfolioModal = ref(false);
const portfolioToDelete = ref<PortfolioData | null>(null);
const showAddCardModal = ref(false);
const targetPortfolio = ref<PortfolioData | null>(null);

// Computed properties
const totalCards = computed(() => {
  return props.portfolios.reduce((portfolioSum, portfolio) => {
    const cards = portfolio.user_cards || [];
    const cardsSum = cards.reduce((cardSum, card) => {
      return cardSum + (card.pivot?.quantity ?? 1);
    }, 0);

    return portfolioSum + cardsSum;
  }, 0);
});

const totalValue = computed(() => {
  return (
    props.portfolios.reduce(
      (sum, portfolio) => sum + (portfolio.total_value_cents || 0),
      0
    ) / 100
  );
});

const filteredPortfolios = computed(() => {
  let filtered = [...props.portfolios];

  // Search filter
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    filtered = filtered.filter(
      (portfolio) =>
        portfolio.name.toLowerCase().includes(query) ||
        (portfolio.description &&
          portfolio.description.toLowerCase().includes(query))
    );
  }

  // Sort
  filtered.sort((a, b) => {
    switch (sortBy.value) {
      case 'name':
        return a.name.localeCompare(b.name);
      case 'cards':
        return (b.user_cards_count || 0) - (a.user_cards_count || 0);
      case 'value':
        return calculatePortfolioValue(b) - calculatePortfolioValue(a);
      case 'created':
        return (
          new Date(b.created_at).getTime() - new Date(a.created_at).getTime()
        );
      default:
        return 0;
    }
  });

  return filtered;
});

// Methods
const formatCurrency = (value: number | null | undefined, currency = 'EUR') => {
  if (value === null || typeof value === 'undefined') return 'N/A';
  return new Intl.NumberFormat('it-IT', {
    style: 'currency',
    currency: currency,
  }).format(value);
};

const formatDate = (dateString: string) => {
  try {
    const date = new Date(dateString);
    return date.toLocaleDateString('it-IT', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    });
  } catch {
    return 'N/A';
  }
};

const calculatePortfolioValue = (portfolio: PortfolioData) => {
  return (portfolio.total_value_cents || 0) / 100;
};

const getPortfolioTotalCards = (portfolio: PortfolioData) => {
  const cards = portfolio.user_cards || [];
  return cards.reduce((sum, card) => sum + (card.pivot?.quantity ?? 1), 0);
};

const editPortfolio = (portfolio: PortfolioData) => {
  portfolioToEdit.value = portfolio;
  showEditPortfolioModal.value = true;
};

const deletePortfolio = (portfolio: PortfolioData) => {
  portfolioToDelete.value = portfolio;
  showDeletePortfolioModal.value = true;
};

const handlePortfolioDeleted = () => {
  router.reload({ only: ['portfolios'] });
  showDeletePortfolioModal.value = false;
  portfolioToDelete.value = null;
};

const handlePortfolioCreated = () => {
  portfolioStore.fetchPortfolios();
  router.reload({ only: ['portfolios'] });
};

const handlePortfolioUpdated = () => {
  showEditPortfolioModal.value = false;
  portfolioToEdit.value = null;

  success(
    'Portfolio updated successfully!',
    'Portfolio details have been updated.'
  );

  router.reload({ only: ['portfolios'] });
};

const closeCreatePortfolioModal = () => {
  showCreatePortfolioModal.value = false;
};

const closeEditPortfolioModal = () => {
  showEditPortfolioModal.value = false;
  portfolioToEdit.value = null;
};

const closeDeletePortfolioModal = () => {
  showDeletePortfolioModal.value = false;
  portfolioToDelete.value = null;
};

const openAddCardModal = (portfolio: PortfolioData) => {
  targetPortfolio.value = portfolio;
  showAddCardModal.value = true;
};

const closeAddCardModal = () => {
  showAddCardModal.value = false;
  targetPortfolio.value = null;
};

const handleCardAdded = () => {
  showAddCardModal.value = false;
  targetPortfolio.value = null;
  // Show success message
  success('Card added!', 'The card has been successfully added to your portfolio.');
  // Force a reload to ensure portfolios are updated with new cards
  router.reload({
    only: ['portfolios'],
  });
};
</script>

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Smooth hover animations */
.group:hover .group-hover\:text-blue-600 {
  transition: color 0.3s ease;
}

/* Progress bar animation */
.bg-blue-500 {
  transition: width 0.5s ease-in-out;
}
</style>
