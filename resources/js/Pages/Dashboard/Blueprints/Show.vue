<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, onMounted, reactive } from 'vue';
import PriceHistoryChart from '@/Components/PriceHistoryChart.vue';
import axios from 'axios';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import ConditionSelect from '@/Components/Forms/ConditionSelect.vue';
import LanguageSelect from '@/Components/Forms/LanguageSelect.vue';
import Checkbox from '@/Components/Checkbox.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useToast } from '@/composables/useToast';

const props = defineProps({
  blueprint: {
    type: Object,
    required: true,
  },
  pricesByLanguage: {
    type: Object,
    default: () => ({}),
  },
  userIsAdmin: Boolean,
});

// Reactive variables
const showAddToPortfolioModal = ref(false);
const imageLoaded = ref(false);
const imageError = ref(false);
const portfolios = ref([]);
const isLoadingPortfolios = ref(false);
const marketStatistics = ref(null);
const isLoadingMarketStats = ref(false);

// New state management for language-specific charts
const chartDataByLanguage = reactive({});
const activeLanguage = ref(null);
const activePeriod = ref('1M'); // Default period
const timePeriods = ['1D', '1W', '1M', '3M', '1Y', 'MAX'];

// Language mapping and flags
const languageDetails = {
  en: { name: 'English', flag: '🇬🇧' },
  jp: { name: 'Japanese', flag: '🇯🇵' },
  ja: { name: 'Japanese', flag: '🇯🇵' },
  fr: { name: 'French', flag: '🇫🇷' },
  ko: { name: 'Korean', flag: '🇰🇷' },
  'zh-cn': { name: 'Chinese (Simplified)', flag: '🇨🇳' },
  'zh-tw': { name: 'Chinese (Traditional)', flag: '��' },
};

// Toast system
const { success, error } = useToast();

const form = reactive({
  portfolio_id: null,
  condition: 'Near Mint',
  language: 'en',
  quantity: 1,
  purchase_price_cents: props.blueprint.latest_price?.price_cents ?? 0,
  is_foil: false,
  errors: {},
});

const purchasePriceEuros = computed({
  get: () => form.purchase_price_cents / 100,
  set: (value) => {
    const numValue = parseFloat(value);
    if (isNaN(numValue)) {
      form.purchase_price_cents = 0;
    } else {
      form.purchase_price_cents = Math.round(numValue * 100);
    }
  },
});

// Language mapping for display names
const languageNames = {
  en: 'English',
  jp: 'Japanese',
  fr: 'French',
  'zh-cn': 'Chinese (Simplified)',
  'zh-tw': 'Chinese (Traditional)',
  ko: 'Korean',
};

// Condition hierarchy for proper ordering
const conditionOrder = [
  'Near Mint',
  'Slightly Played',
  'Lightly Played',
  'Moderately Played',
  'Played',
  'Heavily Played',
  'Poor',
  'Damaged',
];

// Computed properties for price data
const availableLanguages = computed(() => {
  return Object.keys(props.pricesByLanguage)
    .map((lang) => ({
      code: lang,
      name: languageNames[lang] || lang.toUpperCase(),
      hasData: Object.keys(props.pricesByLanguage[lang] || {}).length > 0,
    }))
    .sort((a, b) => a.name.localeCompare(b.name));
});

const selectedLanguagePrices = computed(() => {
  const langPrices = props.pricesByLanguage[activeLanguage.value] || {};

  // Sort conditions by the defined order
  const sortedConditions = Object.keys(langPrices).sort((a, b) => {
    const indexA = conditionOrder.indexOf(a);
    const indexB = conditionOrder.indexOf(b);

    // If condition not found in order, put it at the end
    if (indexA === -1) return 1;
    if (indexB === -1) return -1;

    return indexA - indexB;
  });

  return sortedConditions.map((condition) => ({
    condition,
    ...langPrices[condition],
  }));
});

const hasAnyPriceData = computed(() => {
  return Object.keys(props.pricesByLanguage).length > 0;
});

const selectedLanguageData = computed(() => {
  return availableLanguages.value.find(
    (lang) => lang.code === activeLanguage.value
  );
});

// When component mounts, set the active language to the first available one
onMounted(() => {
  if (
    props.blueprint.available_languages &&
    props.blueprint.available_languages.length > 0
  ) {
    activeLanguage.value = props.blueprint.available_languages[0];
    fetchPriceHistoryForLanguage(activeLanguage.value, activePeriod.value);
    fetchMarketStatistics(activeLanguage.value);
  }
});

const fetchPriceHistoryForLanguage = async (language, period = '1D') => {
  // Create a unique key for language + period combination to allow caching different periods per language
  const cacheKey = `${language}_${period}`;

  // Only prevent re-fetching if we have data for this specific language+period combination
  if (
    chartDataByLanguage[cacheKey]?.data &&
    activeLanguage.value === language &&
    activePeriod.value === period
  ) {
    return;
  }

  // Set loading state for this language+period combination
  chartDataByLanguage[cacheKey] = { isLoading: true, data: null, error: null };
  activeLanguage.value = language;
  activePeriod.value = period;

  // Fetch market statistics when language changes
  fetchMarketStatistics(language);

  try {
    const response = await axios.get(
      route('api.blueprints.price-history', {
        blueprint: props.blueprint.id,
        language: language,
        period: period,
        condition: 'Near Mint', // Specify the condition
      })
    );
    const history = response.data;

    if (history.length === 0) {
      chartDataByLanguage[cacheKey].data = null;
    } else {
      const labels = history.map((p) => formatDateShort(p.fetched_at));
      const data = history.map((p) => p.price_cents / 100);

      chartDataByLanguage[cacheKey].data = {
        labels,
        datasets: [
          {
            label: `Price (EUR)`,
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            borderColor: 'rgba(59, 130, 246, 1)',
            data,
            tension: 0.1,
            fill: true,
            borderWidth: 2,
            pointRadius: 0,
            pointHoverRadius: 6,
            pointHoverBorderWidth: 2,
            pointHoverBorderColor: 'rgba(59, 130, 246, 1)',
            pointHoverBackgroundColor: 'white',
          },
        ],
      };
    }
  } catch (err) {
    console.error(
      `Failed to fetch price history for ${language} (${period}):`,
      err
    );
    chartDataByLanguage[cacheKey].error = 'Failed to load chart data.';
  } finally {
    chartDataByLanguage[cacheKey].isLoading = false;
  }
};

const fetchMarketStatistics = async (language) => {
  if (!language) return;

  isLoadingMarketStats.value = true;

  try {
    const response = await axios.get(
      route('api.blueprints.market-statistics', {
        blueprint: props.blueprint.id,
        language: language,
        condition: 'Near Mint',
      })
    );

    marketStatistics.value = response.data.data;
    console.log('Market statistics loaded:', marketStatistics.value);
  } catch (err) {
    console.error(`Failed to fetch market statistics for ${language}:`, err);
    marketStatistics.value = null;
  } finally {
    isLoadingMarketStats.value = false;
  }
};

const priceStatistics = computed(() => {
  const cacheKey = `${activeLanguage.value}_${activePeriod.value}`;
  const historyData = chartDataByLanguage[cacheKey]?.data?.datasets[0]?.data;

  if (!historyData || historyData.length < 1) {
    // Try to get at least the latest price from props as a fallback
    const latestPrice =
      props.pricesByLanguage[activeLanguage.value]?.['Near Mint']?.price_cents;
    return {
      latestPrice: latestPrice ? formatPrice(latestPrice) : 'N/A',
      change: 'N/A',
      percentage: 'N/A',
      positive: true,
    };
  }

  const latestPrice = historyData[historyData.length - 1];
  // Use first point in the current view for comparison
  const previousPrice = historyData[0];

  const change = latestPrice - previousPrice;
  const percentage = previousPrice !== 0 ? (change / previousPrice) * 100 : 0;

  return {
    latestPrice: formatPrice(latestPrice * 100),
    change: formatPrice(change * 100, undefined, true),
    percentage: `${percentage.toFixed(1)}%`,
    positive: change >= 0,
  };
});

// Helper for formatting price with explicit sign
const formatPriceWithSign = (cents, currency = 'EUR', showSign = false) => {
  const value = cents / 100;
  const options = { style: 'currency', currency: currency };
  if (showSign) {
    options.signDisplay = 'exceptZero';
  }
  return value.toLocaleString('it-IT', options);
};

// Keep old formatPrice for other uses
const formatPrice = (cents, currency = 'EUR') => {
  if (cents === null || cents === undefined) return 'N/A';
  return (cents / 100).toLocaleString('it-IT', {
    style: 'currency',
    currency: currency,
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

const getConditionColor = (condition) => {
  const conditionLower = condition.toLowerCase();

  if (conditionLower.includes('mint') || conditionLower.includes('near mint'))
    return 'green';
  if (
    conditionLower.includes('excellent') ||
    conditionLower.includes('lightly') ||
    conditionLower.includes('slightly')
  )
    return 'blue';
  if (conditionLower.includes('moderate') || conditionLower.includes('good'))
    return 'yellow';
  if (
    conditionLower.includes('played') ||
    conditionLower.includes('poor') ||
    conditionLower.includes('damaged') ||
    conditionLower.includes('heavily')
  )
    return 'red';
  return 'gray';
};

const fetchPortfolios = async () => {
  if (portfolios.value.length > 0) return; // Don't refetch
  isLoadingPortfolios.value = true;
  try {
    const response = await axios.get(route('api.portfolios.index'));
    // Handle both possible response structures
    portfolios.value = response.data.data || response.data;
    console.log('Portfolios response:', response.data); // Debug log
    if (portfolios.value.length > 0) {
      form.portfolio_id = portfolios.value[0].id;
    }
  } catch (error) {
    console.error('Failed to fetch portfolios:', error);
    // Show error toast
    error(
      'Error loading portfolios',
      'Failed to load your portfolios. Please try again.'
    );
  } finally {
    isLoadingPortfolios.value = false;
  }
};

// Methods
const addToPortfolio = () => {
  // Reset errors
  form.errors = {};

  // Ensure we have a valid language - use activeLanguage if available, otherwise first available language
  const validLanguage =
    activeLanguage.value &&
    props.blueprint.available_languages.includes(activeLanguage.value)
      ? activeLanguage.value
      : props.blueprint.available_languages[0];

  // Set default values based on selected language and available prices
  if (validLanguage && props.pricesByLanguage[validLanguage]) {
    const langPrices = props.pricesByLanguage[validLanguage];
    const conditions = Object.keys(langPrices).sort((a, b) => {
      const indexA = conditionOrder.indexOf(a);
      const indexB = conditionOrder.indexOf(b);
      if (indexA === -1) return 1;
      if (indexB === -1) return -1;
      return indexA - indexB;
    });

    if (conditions.length > 0) {
      const bestCondition = conditions[0];
      const bestConditionPrice = langPrices[bestCondition];

      form.language = validLanguage;
      form.condition = bestCondition;
      form.purchase_price_cents = bestConditionPrice.price_cents;
      form.is_foil = bestConditionPrice.is_foil || false;
    }
  } else if (props.blueprint.latest_price) {
    // Fallback to latest price if no data for selected language
    form.language = props.blueprint.latest_price.language;
    form.condition = props.blueprint.latest_price.condition;
    form.purchase_price_cents = props.blueprint.latest_price.price_cents;
    form.is_foil = props.blueprint.latest_price.is_foil || false;
  } else {
    // Final fallback - set default language and basic values
    form.language = validLanguage || 'en';
    form.condition = 'Near Mint';
    form.purchase_price_cents = 0;
    form.is_foil = false;
  }

  console.log('Opening modal with form:', form); // Debug log
  showAddToPortfolioModal.value = true;
  fetchPortfolios();
};

const submitAddToPortfolio = () => {
  form.errors = {};

  // Find the selected portfolio name for better user feedback
  const selectedPortfolio = portfolios.value.find(
    (p) => p.id === form.portfolio_id
  );
  const portfolioName = selectedPortfolio?.name || 'portfolio';

  axios
    .post(
      route('api.portfolios.addBlueprint', { portfolio: form.portfolio_id }),
      {
        blueprint_id: props.blueprint.id,
        ...form,
      }
    )
    .then((response) => {
      showAddToPortfolioModal.value = false;

      // Show success toast
      success(
        'Card added to portfolio!',
        `"${props.blueprint.name}" has been successfully added to "${portfolioName}".`
      );

      console.log('Card added successfully', response.data);
      // Optionally, redirect or update UI
    })
    .catch((error) => {
      if (error.response && error.response.status === 422) {
        form.errors = error.response.data.errors;

        // Show validation error toast
        error(
          'Validation errors',
          'Please check the highlighted fields and try again.'
        );
      } else {
        console.error('An unexpected error occurred:', error);

        // Show generic error toast
        error(
          'Error adding card',
          'An unexpected error occurred. Please try again later.'
        );
      }
    });
};

onMounted(() => {
  // The existing onMounted for price history is replaced by the new one at the top.
});
</script>

<template>
  <Head :title="`${blueprint.name} - Blueprint Details`" />

  <AuthenticatedLayout>
    <!-- Hero Section with Image and Main Info -->
    <div
      class="relative bg-gradient-to-r from-red-600 via-orange-600 to-yellow-600 text-white overflow-hidden"
    >
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
      <div
        class="absolute inset-0 bg-gradient-to-r from-red-600/60 via-orange-600/60 to-yellow-600/60"
      />

      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
        <!-- Language Selector in Hero -->
        <div
          v-if="
            blueprint.available_languages &&
            blueprint.available_languages.length > 1
          "
          class="absolute top-3 right-4 z-10"
        >
          <div
            class="flex items-center space-x-1 bg-white/20 backdrop-blur-sm p-1 rounded-lg"
          >
            <button
              v-for="lang in blueprint.available_languages"
              :key="lang"
              :class="[
                'px-2 py-1 text-sm font-medium rounded-md flex items-center space-x-2 transition-all duration-200',
                activeLanguage === lang
                  ? 'bg-white/90 text-red-700 shadow'
                  : 'text-white/80 hover:bg-white/40',
              ]"
              @click="fetchPriceHistoryForLanguage(lang, activePeriod)"
            >
              <span>{{ languageDetails[lang]?.flag || '🏳️' }}</span>
              <span class="hidden sm:inline">{{
                languageDetails[lang]?.name || lang.toUpperCase()
              }}</span>
            </button>
          </div>
        </div>

        <!-- Breadcrumbs -->
        <nav class="flex items-center space-x-2 text-red-100 mb-3">
          <Link
            :href="route('dashboard')"
            class="hover:text-white transition-colors text-xs"
          >
            Dashboard
          </Link>
          <svg
            class="h-3 w-3"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M9 5l7 7-7 7"
            />
          </svg>
          <Link
            :href="route('blueprints.index')"
            class="hover:text-white transition-colors text-xs"
          >
            Blueprints
          </Link>
          <svg
            class="h-3 w-3"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M9 5l7 7-7 7"
            />
          </svg>
          <span class="text-white font-medium text-xs">{{
            blueprint.name
          }}</span>
        </nav>

        <!-- Main Content -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 items-center">
          <!-- Card Image -->
          <div class="lg:col-span-1">
            <div class="relative group max-w-[120px] mx-auto lg:mx-0">
              <div
                class="bg-white p-2 rounded-lg shadow-lg transform group-hover:scale-105 transition-transform duration-300 border-2 border-white/20"
              >
                <img
                  :src="
                    blueprint.image_url ? blueprint.image_url : placeholderImage
                  "
                  :alt="blueprint.name"
                  class="w-full h-auto object-contain rounded"
                  @load="imageLoaded = true"
                  @error="
                    imageError = true;
                    $event.target.src = placeholderImage;
                  "
                />
              </div>
            </div>
          </div>

          <!-- Main Info -->
          <div class="lg:col-span-3">
            <div class="space-y-3">
              <!-- Title and Price -->
              <div>
                <h1
                  class="text-xl lg:text-2xl font-bold mb-2 text-white drop-shadow-lg"
                >
                  {{ blueprint.name }}
                </h1>
                <div class="flex flex-wrap items-center gap-3">
                  <!-- <div
                    v-if="blueprint.latest_price"
                    class="bg-white/90 backdrop-blur-sm rounded-lg px-3 py-1 shadow-lg"
                  >
                    <p class="text-lg lg:text-xl font-bold text-red-700">
                      {{
                        formatPrice(
                          blueprint.latest_price.price_cents,
                          blueprint.latest_price.currency
                        )
                      }}
                    </p>
                    <p class="text-xs text-red-600">Market Price</p>
                  </div> -->
                  <div class="flex flex-wrap gap-1">
                    <span
                      v-if="blueprint.expansion"
                      class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-white/90 text-red-800 shadow-sm"
                    >
                      {{ blueprint.expansion.name }}
                    </span>

                    <span
                      v-if="
                        blueprint.latest_price && blueprint.latest_price.is_foil
                      "
                      class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-white/90 text-yellow-800 shadow-sm"
                    >
                      ✨ Foil
                    </span>
                  </div>
                </div>
              </div>

              <!-- Quick Actions -->
              <div class="flex flex-wrap gap-2">
                <!-- <button
                  class="inline-flex items-center px-3 py-1.5 bg-white/95 backdrop-blur-sm text-red-600 font-semibold rounded-md shadow-lg hover:bg-white transition-colors duration-200 text-xs"
                  @click="addToPortfolio"
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
                      d="M12 6v6m0 0v6m0-6h6m-6 0H6"
                    />
                  </svg>
                  Add to Portfolio
                </button> -->
                <!-- <button
                  class="inline-flex items-center px-3 py-1.5 bg-white/20 backdrop-blur-sm text-white font-medium rounded-md hover:bg-white/30 transition-colors duration-200 text-xs border border-white/30"
                  @click="copyToClipboard(blueprint.ct_id)"
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
                      d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                    />
                  </svg>
                  Copy ID
                </button> -->
                <Link
                  :href="route('blueprints.index')"
                  class="inline-flex items-center px-3 py-1.5 bg-white/20 backdrop-blur-sm text-white font-medium rounded-md hover:bg-white/30 transition-colors duration-200 text-xs border border-white/30"
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
                      d="M10 19l-7-7m0 0l7-7m-7 7h18"
                    />
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
            <div
              class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700"
            >
              <h3
                class="text-xl font-semibold text-gray-900 dark:text-white mb-4 flex items-center"
              >
                <svg
                  class="h-5 w-5 mr-2 text-blue-600"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                  />
                </svg>
                Card Information
              </h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <p
                    class="text-sm font-medium text-gray-500 dark:text-gray-400"
                  >
                    Card Name
                  </p>
                  <p
                    class="text-lg font-semibold text-gray-900 dark:text-white mt-1"
                  >
                    {{ blueprint.name }}
                  </p>
                </div>
                <div>
                  <p
                    class="text-sm font-medium text-gray-500 dark:text-gray-400"
                  >
                    Version
                  </p>
                  <p class="text-lg text-gray-900 dark:text-white mt-1">
                    {{ blueprint.version || 'N/A' }}
                  </p>
                </div>
                <div>
                  <p
                    class="text-sm font-medium text-gray-500 dark:text-gray-400"
                  >
                    Expansion
                  </p>
                  <p class="text-lg text-gray-900 dark:text-white mt-1">
                    {{ blueprint.expansion?.name || 'N/A' }}
                  </p>
                </div>
                <div>
                  <p
                    class="text-sm font-medium text-gray-500 dark:text-gray-400"
                  >
                    Category
                  </p>
                  <p class="text-lg text-gray-900 dark:text-white mt-1">
                    {{ blueprint.category?.name || 'N/A' }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Price History Chart -->
            <div
              class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden"
            >
              <!-- Chart Header with Price and Stats -->
              <div
                class="px-6 py-4 border-b border-gray-100 dark:border-gray-700"
              >
                <div v-if="activeLanguage" class="space-y-2">
                  <!-- Main Price -->
                  <div class="text-3xl font-bold text-gray-900 dark:text-white">
                    {{ priceStatistics.latestPrice }}
                    <span class="text-xs font-normal text-gray-400 ml-1"
                      >€</span
                    >
                  </div>

                  <!-- Stats Row -->
                  <div class="flex items-center space-x-6 text-sm">
                    <div>
                      <span
                        class="text-gray-500 dark:text-gray-400 uppercase tracking-wide text-xs font-medium"
                      >
                        {{
                          activePeriod === '1D'
                            ? 'TODAY'
                            : activePeriod === '1W'
                              ? 'THIS WEEK'
                              : activePeriod === '1M'
                                ? 'THIS MONTH'
                                : activePeriod === '3M'
                                  ? 'LAST 3 MONTHS'
                                  : activePeriod === '1Y'
                                    ? 'THIS YEAR'
                                    : 'ALL TIME'
                        }}
                      </span>
                      <div
                        :class="[
                          'font-semibold',
                          priceStatistics.positive
                            ? 'text-green-600'
                            : 'text-red-600',
                        ]"
                      >
                        {{ priceStatistics.change }}
                      </div>
                    </div>

                    <div>
                      <span
                        class="text-gray-500 dark:text-gray-400 uppercase tracking-wide text-xs font-medium"
                      >
                        RATE OF RETURN
                      </span>
                      <div
                        :class="[
                          'font-semibold',
                          priceStatistics.positive
                            ? 'text-green-600'
                            : 'text-red-600',
                        ]"
                      >
                        {{ priceStatistics.percentage }}
                      </div>
                    </div>
                  </div>
                </div>

                <div v-else class="py-4">
                  <p class="text-gray-500">
                    Select a language to view its price history.
                  </p>
                </div>
              </div>

              <!-- Chart Container -->
              <div class="relative">
                <div
                  v-if="!activeLanguage"
                  class="h-64 flex items-center justify-center"
                >
                  <p class="text-gray-500">
                    Select a language to view its price history.
                  </p>
                </div>

                <div
                  v-else-if="
                    chartDataByLanguage[`${activeLanguage}_${activePeriod}`]
                      ?.isLoading
                  "
                  class="h-64 flex items-center justify-center"
                >
                  <div class="text-center">
                    <svg
                      class="animate-spin h-8 w-8 text-gray-400 dark:text-gray-600 mx-auto mb-2"
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
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                      Loading price history...
                    </p>
                  </div>
                </div>

                <div
                  v-else-if="
                    chartDataByLanguage[`${activeLanguage}_${activePeriod}`]
                      ?.data
                  "
                  class="h-64 px-2"
                >
                  <PriceHistoryChart
                    :chart-data="
                      chartDataByLanguage[`${activeLanguage}_${activePeriod}`]
                        .data
                    "
                    :chart-options="{
                      responsive: true,
                      maintainAspectRatio: false,
                      layout: {
                        padding: {
                          top: 20,
                          right: 20,
                          bottom: 10,
                          left: 10,
                        },
                      },
                      plugins: {
                        legend: {
                          display: false,
                        },
                        tooltip: {
                          mode: 'index',
                          intersect: false,
                          backgroundColor: 'rgba(17, 24, 39, 0.9)',
                          titleColor: 'white',
                          bodyColor: 'white',
                          borderColor: 'rgba(59, 130, 246, 1)',
                          borderWidth: 1,
                          cornerRadius: 8,
                          displayColors: false,
                          callbacks: {
                            title: function (context) {
                              return context[0].label;
                            },
                            label: function (context) {
                              return '€' + context.parsed.y.toFixed(2);
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
                              size: 11,
                            },
                            maxTicksLimit: 6,
                          },
                          border: {
                            display: false,
                          },
                        },
                        y: {
                          display: true,
                          position: 'right',
                          grid: {
                            color: 'rgba(229, 231, 235, 0.5)',
                            drawBorder: false,
                          },
                          ticks: {
                            color: 'rgba(156, 163, 175, 0.8)',
                            font: {
                              size: 11,
                            },
                            callback: function (value) {
                              if (value >= 1000) {
                                return (value / 1000).toFixed(1) + 'K';
                              }
                              return '€' + value.toFixed(0);
                            },
                            maxTicksLimit: 6,
                          },
                          border: {
                            display: false,
                          },
                        },
                      },
                      elements: {
                        line: {
                          tension: 0.1,
                          borderWidth: 2,
                          borderColor: 'rgba(59, 130, 246, 1)',
                          backgroundColor: 'rgba(59, 130, 246, 0.1)',
                          fill: true,
                        },
                        point: {
                          radius: 0,
                          hoverRadius: 6,
                          hoverBorderWidth: 2,
                          hoverBorderColor: 'rgba(59, 130, 246, 1)',
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
                    chartDataByLanguage[`${activeLanguage}_${activePeriod}`]
                      ?.error
                  "
                  class="h-64 flex items-center justify-center"
                >
                  <p class="text-red-500">
                    {{
                      chartDataByLanguage[`${activeLanguage}_${activePeriod}`]
                        .error
                    }}
                  </p>
                </div>

                <div v-else class="h-64 flex items-center justify-center">
                  <div class="text-center">
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
                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2-2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                      />
                    </svg>
                    <h4
                      class="text-lg font-semibold text-gray-900 dark:text-white mb-2"
                    >
                      No Price History
                    </h4>
                    <p class="text-gray-600 dark:text-gray-400 text-sm">
                      Price history data is not available for this card yet.
                    </p>
                  </div>
                </div>
              </div>

              <!-- Time Period Filters -->
              <div
                class="px-6 py-4 bg-gray-50 dark:bg-gray-700/50 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between"
              >
                <div class="flex space-x-1">
                  <button
                    v-for="period in timePeriods"
                    :key="period"
                    :class="[
                      'px-3 py-1.5 text-sm font-medium rounded-md transition-all duration-200',
                      activePeriod === period
                        ? 'bg-gray-800 text-white shadow-sm'
                        : 'text-gray-600 hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-600',
                    ]"
                    @click="
                      fetchPriceHistoryForLanguage(activeLanguage, period)
                    "
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
            </div>
          </div>

          <!-- Right Column - Price & Market Info -->
          <div class="space-y-6">
            <!-- Prices by Condition -->
            <div
              v-if="hasAnyPriceData"
              class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700"
            >
              <div class="flex items-center justify-between mb-4">
                <h3
                  class="text-lg font-semibold text-gray-900 dark:text-white flex items-center"
                >
                  <svg
                    class="h-5 w-5 mr-2 text-blue-600"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"
                    />
                  </svg>
                  Prices by Condition
                </h3>
              </div>

              <!-- Price Data for Selected Language -->
              <div v-if="selectedLanguagePrices.length > 0" class="space-y-3">
                <div
                  v-for="price in selectedLanguagePrices"
                  :key="`${price.condition}-${price.language}`"
                  class="bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-900 dark:to-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700 hover:shadow-md transition-shadow duration-200"
                >
                  <div class="flex items-center justify-between">
                    <div class="flex-1">
                      <div class="flex items-center space-x-2 mb-2">
                        <span
                          :class="`inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-${getConditionColor(price.condition)}-100 text-${getConditionColor(price.condition)}-800 dark:bg-${getConditionColor(price.condition)}-900 dark:text-${getConditionColor(price.condition)}-200`"
                        >
                          {{ price.condition }}
                        </span>
                        <span
                          v-if="price.is_foil"
                          class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200"
                        >
                          ✨ Foil
                        </span>
                      </div>
                      <div class="text-xs text-gray-500 dark:text-gray-400">
                        Updated {{ formatDateShort(price.fetched_at) }}
                      </div>
                    </div>
                    <div class="text-right">
                      <div
                        class="text-2xl font-bold text-green-600 dark:text-green-400"
                      >
                        {{ formatPrice(price.price_cents, price.currency) }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div v-else class="text-center py-8">
                <p class="text-gray-500 dark:text-gray-400">
                  No price data for this language.
                </p>
              </div>

              <!-- Add to Portfolio button -->
              <div
                class="pt-4 border-t border-gray-200 dark:border-gray-700 mt-4"
              >
                <button
                  class="w-full px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg transition-colors duration-200 flex items-center justify-center"
                  @click="addToPortfolio"
                >
                  <svg
                    class="h-4 w-4 mr-2"
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
                  Add to Portfolio
                </button>
              </div>
            </div>

            <!-- No Price Data At All -->
            <div
              v-else
              class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700"
            >
              <div class="text-center py-8">
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
                    d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"
                  />
                </svg>
                <h3
                  class="text-lg font-semibold text-gray-900 dark:text-white mb-2"
                >
                  No Price Data
                </h3>
                <p class="text-gray-600 dark:text-gray-400 text-sm">
                  Price information is not available for this card yet.
                </p>
              </div>
            </div>

            <!-- Market Statistics -->
            <div
              class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700"
            >
              <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                  Market Statistics
                </h3>
                <div
                  v-if="isLoadingMarketStats"
                  class="animate-spin h-4 w-4 border-2 border-blue-600 border-t-transparent rounded-full"
                />
              </div>

              <div v-if="marketStatistics" class="space-y-4">
                <div class="flex items-center justify-between">
                  <span class="text-sm text-gray-500 dark:text-gray-400"
                    >Market Cap</span
                  >
                  <span
                    class="text-sm font-semibold text-gray-900 dark:text-white"
                  >
                    {{ marketStatistics.market_cap_formatted }}
                  </span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-sm text-gray-500 dark:text-gray-400"
                    >Avg. Price (30d)</span
                  >
                  <span
                    class="text-sm font-semibold text-gray-900 dark:text-white"
                  >
                    {{ marketStatistics.avg_price_30d_formatted }}
                  </span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-sm text-gray-500 dark:text-gray-400"
                    >Price Change (7d)</span
                  >
                  <span
                    :class="[
                      'text-sm font-semibold',
                      marketStatistics.price_change_7d_percentage > 0
                        ? 'text-green-600'
                        : marketStatistics.price_change_7d_percentage < 0
                          ? 'text-red-600'
                          : 'text-gray-900 dark:text-white',
                    ]"
                  >
                    {{ marketStatistics.price_change_7d_formatted }}
                  </span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-sm text-gray-500 dark:text-gray-400"
                    >Popularity Rank</span
                  >
                  <span
                    class="text-sm font-semibold text-gray-900 dark:text-white"
                  >
                    #{{ marketStatistics.popularity_rank }}
                  </span>
                </div>
              </div>

              <div v-else-if="!isLoadingMarketStats" class="space-y-4">
                <div class="flex items-center justify-between">
                  <span class="text-sm text-gray-500 dark:text-gray-400"
                    >Market Cap</span
                  >
                  <span class="text-sm font-semibold text-gray-500">N/A</span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-sm text-gray-500 dark:text-gray-400"
                    >Avg. Price (30d)</span
                  >
                  <span class="text-sm font-semibold text-gray-500">N/A</span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-sm text-gray-500 dark:text-gray-400"
                    >Price Change (7d)</span
                  >
                  <span class="text-sm font-semibold text-gray-500">N/A</span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-sm text-gray-500 dark:text-gray-400"
                    >Popularity Rank</span
                  >
                  <span class="text-sm font-semibold text-gray-500">N/A</span>
                </div>
              </div>

              <div v-else class="space-y-4">
                <div
                  v-for="i in 4"
                  :key="i"
                  class="flex items-center justify-between"
                >
                  <span class="text-sm text-gray-500 dark:text-gray-400"
                    >Loading...</span
                  >
                  <div
                    class="h-4 w-16 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"
                  />
                </div>
              </div>
            </div>

            <!-- Technical Details -->
            <div
              v-if="userIsAdmin"
              class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700"
            >
              <h3
                class="text-lg font-semibold text-gray-900 dark:text-white mb-4"
              >
                Technical Details
              </h3>
              <div class="space-y-3 text-sm">
                <div class="flex items-center justify-between">
                  <span class="text-gray-500 dark:text-gray-400"
                    >Database ID</span
                  >
                  <span class="font-mono text-gray-900 dark:text-white">{{
                    blueprint.id
                  }}</span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-gray-500 dark:text-gray-400"
                    >Last Updated</span
                  >
                  <span class="text-gray-900 dark:text-white">{{
                    formatDateShort(blueprint.updated_at)
                  }}</span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-gray-500 dark:text-gray-400"
                    >Data Source</span
                  >
                  <span class="text-gray-900 dark:text-white">CardTrader</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Add to Portfolio Modal -->
    <Modal
      :show="showAddToPortfolioModal"
      @close="showAddToPortfolioModal = false"
    >
      <div class="p-6">
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
          Add "{{ blueprint.name }}" to Portfolio
        </h2>
        <p
          v-if="form.language"
          class="text-sm text-gray-600 dark:text-gray-400 mt-1"
        >
          Language:
          <strong>{{
            languageNames[form.language] || form.language.toUpperCase()
          }}</strong>
          <span v-if="form.condition">• {{ form.condition }}</span>
          <span v-if="form.is_foil" class="text-yellow-600 dark:text-yellow-400"
            >• ✨ Foil</span
          >
        </p>

        <form class="mt-6 space-y-4" @submit.prevent="submitAddToPortfolio">
          <div>
            <InputLabel for="portfolio" value="Portfolio" />
            <select
              id="portfolio"
              v-model="form.portfolio_id"
              class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
              :disabled="isLoadingPortfolios"
            >
              <option v-if="isLoadingPortfolios" value="" disabled>
                Loading portfolios...
              </option>
              <option v-else-if="portfolios.length === 0" value="" disabled>
                No portfolios found
              </option>
              <option v-else value="" disabled>Select a portfolio</option>
              <option
                v-for="portfolio in portfolios"
                :key="portfolio.id"
                :value="portfolio.id"
              >
                {{ portfolio.name }}
              </option>
            </select>
            <InputError class="mt-2" :message="form.errors.portfolio_id" />
          </div>

          <div>
            <ConditionSelect
              id="condition"
              v-model="form.condition"
              class="mt-1 block w-full"
            />
            <InputError class="mt-2" :message="form.errors.condition" />
          </div>

          <div>
            <InputLabel for="language" value="Language" />
            <select
              id="language"
              v-model="form.language"
              class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
            >
              <option value="" disabled>Select a language</option>
              <option
                v-for="lang in blueprint.available_languages"
                :key="lang"
                :value="lang"
              >
                {{ languageDetails[lang]?.flag || '🏳️' }}
                {{ languageDetails[lang]?.name || lang.toUpperCase() }}
              </option>
            </select>
            <InputError class="mt-2" :message="form.errors.language" />
          </div>

          <div>
            <InputLabel for="quantity" value="Quantity" />
            <TextInput
              id="quantity"
              v-model="form.quantity"
              type="number"
              class="mt-1 block w-full"
              min="1"
            />
            <InputError class="mt-2" :message="form.errors.quantity" />
          </div>

          <div>
            <InputLabel for="purchase_price" value="Purchase Price (€)" />
            <TextInput
              id="purchase_price"
              v-model="purchasePriceEuros"
              type="number"
              class="mt-1 block w-full"
              min="0"
              step="0.01"
            />
            <InputError
              class="mt-2"
              :message="form.errors.purchase_price_cents"
            />
          </div>

          <div class="block">
            <label class="flex items-center">
              <Checkbox v-model:checked="form.is_foil" name="is_foil" />
              <span class="ms-2 text-sm text-gray-600 dark:text-gray-400"
                >Foil</span
              >
            </label>
            <InputError class="mt-2" :message="form.errors.is_foil" />
          </div>

          <div
            class="flex items-center justify-end mt-6 pt-4 border-t border-gray-200 dark:border-gray-700"
          >
            <SecondaryButton @click="showAddToPortfolioModal = false">
              Cancel
            </SecondaryButton>

            <PrimaryButton class="ms-3"> Add Card </PrimaryButton>
          </div>
        </form>
      </div>
    </Modal>
  </AuthenticatedLayout>
</template>

<style scoped>
/* Custom animations and transitions */
.group:hover .group-hover\:scale-105 {
  transform: scale(1.05);
}
</style>
