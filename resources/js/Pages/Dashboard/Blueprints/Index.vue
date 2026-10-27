<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch, reactive, computed } from 'vue';
import axios from 'axios';
import debounce from 'lodash/debounce';
import QuickAddCardModal from '@/Components/QuickAddCardModal.vue';
import BlueprintCard from '@/Components/BlueprintCard.vue';
import BlueprintSearchBar from '@/Components/BlueprintSearchBar.vue';

// --- Types ---
interface Blueprint {
  id: number;
  name: string;
  image_url?: string;
  ct_id?: string;
  collector_number?: string;
  expansion?: {
    id: number;
    name: string;
    code: string;
  };
  latest_price?: {
    price_cents: number;
    currency: string;
    condition: string;
    language?: string;
    is_foil?: boolean;
  };
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

// --- Props ---
const props = defineProps<{
  blueprints: PaginatedBlueprints;
  filters: {
    expansion_id?: number | null;
    sort?: string | null;
  };
  availableExpansions: Expansion[];
}>();

// --- State ---
const currentFilters = reactive({
  expansion: props.filters.expansion_id || '',
  sort: props.filters.sort || '',
});
const showFilters = ref(false);
const searchQuery = ref('');
const isSearching = ref(false);
const searchResults = ref<Blueprint[]>([]);
const isSearchMode = ref(false);
const searchPage = ref(1);
const hasMoreSearchResults = ref(false);
const viewMode = ref<'grid' | 'list'>('grid');
const searchInputRef = ref<HTMLInputElement | null>(null);
const showAddToPortfolioModal = ref(false);
const selectedBlueprint = ref<Blueprint | null>(null);
// Language selection (string code; default EN)
// Default EN; server may not provide language in filters yet
const searchLanguageCode = ref<'en' | ''>(
  (props as any).filters?.language || 'en'
);

// --- Constants ---
const sortOptions = [
  { value: '', label: 'Default' },
  { value: 'price_asc', label: 'Price: Low to High' },
  { value: 'price_desc', label: 'Price: High to Low' },
  { value: 'name_asc', label: 'Name: A-Z' },
  { value: 'name_desc', label: 'Name: Z-A' },
];

// --- Computed ---
const activeFiltersCount = computed(() => {
  let count = 0;
  if (currentFilters.expansion) count++;
  if (currentFilters.sort) count++;
  return count;
});
const displayData = computed(() =>
  isSearchMode.value ? searchResults.value : props.blueprints.data
);
const displayTotal = computed(() =>
  isSearchMode.value
    ? searchResults.value.length + (hasMoreSearchResults.value ? '+' : '')
    : props.blueprints.total
);

// --- Methods ---
const clearSearchResults = () => {
  searchResults.value = [];
  isSearchMode.value = false;
  hasMoreSearchResults.value = false;
  searchPage.value = 1;
};

const clearFilters = () => {
  currentFilters.expansion = '';
  currentFilters.sort = '';
  if (!isSearchMode.value) {
    router.get(
      route('blueprints.index'),
      {},
      {
        preserveState: true,
        replace: true,
      }
    );
  }
};

const clearSearch = () => {
  searchQuery.value = '';
  clearSearchResults();
};

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
    const params: Record<string, string | number | undefined> = {
      q: searchQuery.value,
      from: (searchPage.value - 1) * 30,
      size: 30,
    };
    if (currentFilters.expansion)
      params.expansion_id = Number(currentFilters.expansion);
    if (currentFilters.sort) params.sort = currentFilters.sort;
    if (searchLanguageCode.value) params.language = searchLanguageCode.value;

    const { data } = await axios.get('/blueprints/search', { params });
    if (data.success && data.data) {
      if (loadMore) {
        searchResults.value.push(...data.data);
      } else {
        searchResults.value = data.data;
      }
      hasMoreSearchResults.value = data.pagination.has_more;
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

const loadMoreResults = () => {
  if (hasMoreSearchResults.value && !isSearching.value) {
    searchPage.value++;
    searchBlueprints(true);
  }
};

const debouncedSearch = debounce(searchBlueprints, 300);

const handleSearchInput = () => {
  if (searchQuery.value.length >= 2) {
    debouncedSearch();
  } else {
    clearSearchResults();
  }
};

const handleDirectInput = (event: Event) => {
  const target = event.target as HTMLInputElement;
  searchQuery.value = target.value;
  handleSearchInput();
};

const openAddToPortfolioModal = (blueprint: Blueprint) => {
  selectedBlueprint.value = blueprint;
  showAddToPortfolioModal.value = true;
};

const closeAddToPortfolioModal = () => {
  showAddToPortfolioModal.value = false;
  selectedBlueprint.value = null;
};

const handleCardAdded = (event: {
  portfolioId: number;
  cards: unknown[];
  totalQuantity: number;
}) => {
  // Gestisci l'evento di aggiunta carta se necessario
  closeAddToPortfolioModal();
};

// --- Watchers ---
watch(
  currentFilters,
  (newFilters) => {
    if (isSearchMode.value && searchQuery.value.length >= 2) {
      debouncedSearch();
    } else {
      router.get(
        route('blueprints.index'),
        {
          expansion_id: newFilters.expansion || undefined,
          sort: newFilters.sort || undefined,
          language: searchLanguageCode.value || undefined,
        },
        {
          preserveState: true,
          replace: true,
        }
      );
    }
  },
  { deep: true }
);

// Re-run search when language changes (if user is searching)
watch(searchLanguageCode, () => {
  if (searchQuery.value.length >= 2) {
    debouncedSearch();
  }
  if (!isSearchMode.value) {
    router.get(
      route('blueprints.index'),
      {
        expansion_id: currentFilters.expansion || undefined,
        sort: currentFilters.sort || undefined,
        language: searchLanguageCode.value || undefined,
      },
      { preserveState: true, replace: true }
    );
  }
});
</script>

<template>
  <Head title="Blueprints Dashboard" />
  <AuthenticatedLayout>
    <div
      class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700"
    >
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <div
          class="flex flex-col sm:flex-row sm:items-center sm:justify-between"
        >
          <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
              Cards
            </h1>
            <p class="text-gray-500 dark:text-gray-400 mt-1 text-sm">
              {{ displayTotal }} cards
              {{ isSearchMode ? 'found' : 'available' }} •
              {{ displayData.length }} showing
              <span
                v-if="isSearchMode"
                class="text-gray-700 dark:text-gray-300 font-medium"
              >
                for "{{ searchQuery }}"
              </span>
            </p>
          </div>
          <div class="mt-3 sm:mt-0 flex items-center space-x-2">
            <!-- Sort Controls -->
            <div class="flex items-center space-x-2">
              <label class="text-sm text-gray-600 dark:text-gray-300">
                Sort:
              </label>
              <select
                v-model="currentFilters.sort"
                class="px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-button bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-gray-500 transition-colors"
              >
                <option
                  v-for="option in sortOptions"
                  :key="option.value"
                  :value="option.value"
                >
                  {{ option.label }}
                </option>
              </select>
            </div>

            <!-- View Mode Toggle -->
            <div
              class="flex items-center bg-gray-100 dark:bg-gray-700 rounded-button p-0.5"
            >
              <button
                :class="
                  viewMode === 'grid'
                    ? 'bg-white dark:bg-gray-600 shadow-sm'
                    : ''
                "
                class="p-1.5 rounded-button transition-all duration-150"
                @click="viewMode = 'grid'"
              >
                <svg
                  class="h-4 w-4 text-gray-600 dark:text-gray-300"
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
                class="p-1.5 rounded-button transition-all duration-150"
                @click="viewMode = 'list'"
              >
                <svg
                  class="h-4 w-4 text-gray-600 dark:text-gray-300"
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

            <!-- Filters Button -->
            <div class="relative">
              <button
                class="inline-flex items-center px-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-button text-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-1 focus:ring-gray-500 transition-colors"
                @click="showFilters = !showFilters"
              >
                <svg
                  class="h-4 w-4 mr-2 text-gray-500 dark:text-gray-400"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.414A1 1 0 013 6.707V4z"
                  />
                </svg>
                <span
                  class="text-xs font-medium text-gray-700 dark:text-gray-300"
                  >Filters</span
                >
                <span
                  v-if="activeFiltersCount > 0"
                  class="ml-1.5 inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-medium leading-none text-white bg-gray-600 rounded-full"
                >
                  {{ activeFiltersCount }}
                </span>
                <svg
                  class="ml-2 -mr-1 h-4 w-4 text-gray-500 dark:text-gray-400"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    :d="showFilters ? 'M19 9l-7 7-7-7' : 'M9 5l7 7-7 7'"
                  />
                </svg>
              </button>
              <div
                v-if="showFilters"
                class="absolute right-0 mt-1 w-72 bg-white dark:bg-gray-800 rounded-card shadow-card border border-gray-200 dark:border-gray-700 z-50"
              >
                <div class="p-3">
                  <div class="flex items-center justify-between mb-3">
                    <h3
                      class="text-sm font-medium text-gray-900 dark:text-white"
                    >
                      Filters
                    </h3>
                    <button
                      v-if="activeFiltersCount > 0"
                      class="text-xs text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300"
                      @click="clearFilters"
                    >
                      Clear All
                    </button>
                  </div>
                  <div class="space-y-3">
                    <div>
                      <label
                        class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5"
                        >Expansion</label
                      >
                      <select
                        v-model="currentFilters.expansion"
                        class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-button bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-gray-500 transition-colors"
                      >
                        <option value="">All Expansions</option>
                        <option
                          v-for="expansion in availableExpansions"
                          :key="expansion.id"
                          :value="expansion.id"
                        >
                          {{ expansion.name }}
                        </option>
                      </select>
                    </div>
                  </div>
                  <div
                    v-if="activeFiltersCount > 0"
                    class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700"
                  >
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                      Active filters:
                    </p>
                    <div class="flex flex-wrap gap-2">
                      <span
                        v-if="currentFilters.expansion"
                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200"
                      >
                        Expansion:
                        {{
                          availableExpansions.find(
                            (e) => e.id == currentFilters.expansion
                          )?.name
                        }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="mt-4">
          <div class="max-w-xl">
            <div class="relative">
              <div
                class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"
              >
                <svg
                  class="h-4 w-4 text-gray-400 dark:text-gray-500"
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
              <BlueprintSearchBar
                v-model:model-value="searchQuery"
                v-model:language="searchLanguageCode"
                :loading="isSearching"
                placeholder="Search cards..."
                @update:model-value="handleSearchInput()"
                @clear="clearSearch"
              />
            </div>
            <div
              v-if="isSearchMode"
              class="mt-2 flex items-center justify-between"
            >
              <div
                class="flex items-center text-xs text-gray-600 dark:text-gray-400"
              >
                <svg
                  class="h-3 w-3 mr-1"
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
                Search results
              </div>
              <button
                class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-500 dark:hover:text-gray-300"
                @click="clearSearch"
              >
                Clear
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="min-h-screen bg-gray-50 dark:bg-gray-900 py-4">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div
          v-if="isSearching && searchResults.length === 0"
          class="text-center py-12"
        >
          <div
            class="bg-white dark:bg-gray-800 rounded-card p-8 border border-gray-200 dark:border-gray-700"
          >
            <div class="animate-spin h-6 w-6 text-gray-600 mx-auto mb-3">
              <svg fill="none" viewBox="0 0 24 24">
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
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">
              Searching...
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
              Finding cards for "{{ searchQuery }}"
            </p>
          </div>
        </div>
        <div v-else-if="displayData.length > 0">
          <div
            v-if="viewMode === 'grid'"
            class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3"
          >
            <BlueprintCard
              v-for="blueprint in displayData"
              :key="blueprint.id"
              :blueprint="blueprint"
              view-mode="grid"
              @add-to-portfolio="openAddToPortfolioModal"
            />
          </div>
          <div
            v-else
            class="bg-white dark:bg-gray-800 rounded-card border border-gray-200 dark:border-gray-700 overflow-hidden"
          >
            <div class="divide-y divide-gray-200 dark:divide-gray-700">
              <BlueprintCard
                v-for="blueprint in displayData"
                :key="blueprint.id"
                :blueprint="blueprint"
                view-mode="list"
                @add-to-portfolio="openAddToPortfolioModal"
              />
            </div>
          </div>
          <div
            v-if="isSearchMode && hasMoreSearchResults"
            class="mt-6 text-center"
          >
            <button
              :disabled="isSearching"
              class="px-4 py-2 bg-gray-600 text-white text-sm rounded-button hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              @click="loadMoreResults"
            >
              {{ isSearching ? 'Loading...' : 'Load More' }}
            </button>
          </div>
        </div>
        <div v-else class="text-center py-12">
          <div
            class="bg-white dark:bg-gray-800 rounded-card p-8 border border-gray-200 dark:border-gray-700"
          >
            <svg
              class="h-12 w-12 text-gray-400 dark:text-gray-600 mx-auto mb-3"
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
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
              {{ isSearchMode ? 'No cards found' : 'No cards available' }}
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
              {{
                isSearchMode
                  ? `No cards match "${searchQuery}"`
                  : 'Try adjusting your filters'
              }}
            </p>
            <div class="space-x-2">
              <button
                v-if="isSearchMode"
                class="px-3 py-1.5 bg-gray-600 text-white text-sm rounded-button hover:bg-gray-700 transition-colors"
                @click="clearSearch"
              >
                Clear Search
              </button>
              <button
                v-if="activeFiltersCount > 0"
                class="px-3 py-1.5 bg-gray-600 text-white text-sm rounded-button hover:bg-gray-700 transition-colors"
                @click="clearFilters"
              >
                Clear Filters
              </button>
            </div>
          </div>
        </div>
        <div
          v-if="!isSearchMode && blueprints.links.length > 3"
          class="mt-6 flex justify-center"
        >
          <nav
            class="relative z-0 inline-flex rounded-card -space-x-px"
            aria-label="Pagination"
          >
            <template v-for="(link, key) in blueprints.links" :key="key">
              <!-- eslint-disable-next-line vue/no-v-html -->
              <div
                v-if="link.url === null"
                class="relative inline-flex items-center px-4 py-2 border text-sm font-medium bg-gray-100 border-gray-300 text-gray-400 cursor-not-allowed dark:bg-gray-700 dark:border-gray-600 dark:text-gray-500"
                v-html="link.label"
              />
              <Link
                v-else
                :href="link.url"
                class="relative inline-flex items-center px-4 py-2 border text-sm font-medium"
                :class="{
                  'z-10 bg-indigo-50 border-indigo-500 text-indigo-600 dark:bg-indigo-900 dark:border-indigo-700 dark:text-indigo-300':
                    link.active,
                  'bg-white border-gray-300 text-gray-500 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-700':
                    !link.active,
                }"
              >
                <!-- eslint-disable-next-line vue/no-v-text-v-html-on-component, vue/no-v-html -->
                <span v-html="link.label" />
              </Link>
            </template>
          </nav>
        </div>
      </div>
    </div>
    <div
      v-if="showFilters"
      class="fixed inset-0 z-40"
      @click="showFilters = false"
    />

    <!-- Quick Add to Portfolio Modal -->
    <QuickAddCardModal
      v-if="showAddToPortfolioModal && selectedBlueprint"
      :blueprint="selectedBlueprint"
      @close="closeAddToPortfolioModal"
      @card-added="handleCardAdded"
    />
  </AuthenticatedLayout>
</template>
