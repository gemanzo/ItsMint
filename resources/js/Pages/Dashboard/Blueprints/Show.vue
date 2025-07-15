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
const priceHistory = ref(null);
const isLoadingChart = ref(true);
const portfolios = ref([]);
const isLoadingPortfolios = ref(false);
const selectedLanguage = ref('en'); // Default to English

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
  const langPrices = props.pricesByLanguage[selectedLanguage.value] || {};

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
    (lang) => lang.code === selectedLanguage.value
  );
});

// Set default language to the first available one
onMounted(() => {
  if (availableLanguages.value.length > 0) {
    // Try to find English first, otherwise use the first available
    const englishLang = availableLanguages.value.find(
      (lang) => lang.code === 'en'
    );
    selectedLanguage.value = englishLang
      ? englishLang.code
      : availableLanguages.value[0].code;
  }
});

// Placeholder image as data URL SVG
const placeholderImage =
  'data:image/svg+xml;base64,' +
  btoa(`
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
    portfolios.value = response.data.data;
    if (portfolios.value.length > 0) {
      form.portfolio_id = portfolios.value[0].id;
    }
  } catch (error) {
    console.error('Failed to fetch portfolios:', error);
    // Handle error, maybe show a toast
  } finally {
    isLoadingPortfolios.value = false;
  }
};

// Methods
const addToPortfolio = () => {
  // Set default values based on selected language and available prices
  if (selectedLanguagePrices.value.length > 0) {
    const bestConditionPrice = selectedLanguagePrices.value[0]; // First in the sorted array
    form.language = selectedLanguage.value;
    form.condition = bestConditionPrice.condition;
    form.purchase_price_cents = bestConditionPrice.price_cents;
    form.is_foil = bestConditionPrice.is_foil || false;
  } else if (props.blueprint.latest_price) {
    // Fallback to latest price if no data for selected language
    form.language = props.blueprint.latest_price.language;
    form.condition = props.blueprint.latest_price.condition;
    form.purchase_price_cents = props.blueprint.latest_price.price_cents;
    form.is_foil = props.blueprint.latest_price.is_foil || false;
  }

  showAddToPortfolioModal.value = true;
  fetchPortfolios();
};

const submitAddToPortfolio = () => {
  form.errors = {};
  
  // Find the selected portfolio name for better user feedback
  const selectedPortfolio = portfolios.value.find(p => p.id === form.portfolio_id);
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

const fetchPriceHistory = async () => {
  try {
    const response = await axios.get(
      route('api.blueprints.price-history', { blueprint: props.blueprint.id })
    );
    const history = response.data;

    if (history.length === 0) {
      priceHistory.value = null;
      return;
    }

    const labels = history.map((p) => formatDateShort(p.fetched_at));
    const data = history.map((p) => p.price_cents / 100);

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
            :href="route('dashboard.blueprints.index')"
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
                  :href="route('dashboard.blueprints.index')"
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

            <!-- Price History (Placeholder) -->
            <div
              class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700"
            >
              <h3
                class="text-xl font-semibold text-gray-900 dark:text-white mb-4 flex items-center"
              >
                <svg
                  class="h-5 w-5 mr-2 text-green-600"
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
                Price History
              </h3>
              <div
                class="h-40 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center"
              >
                <div class="text-center">
                  <svg
                    class="h-8 w-8 text-gray-400 dark:text-gray-600 mx-auto mb-2"
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
                  <p class="text-sm text-gray-500 dark:text-gray-400">
                    Price history chart coming soon
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Right Column - Price & Market Info -->
          <div class="space-y-6">
            <!-- Multi-Language Price Display -->
            <div
              v-if="hasAnyPriceData"
              class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700"
            >
              <div class="flex items-center justify-between mb-6">
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
                  Prices by Language
                </h3>
              </div>

              <!-- Language Selector -->
              <div class="mb-6">
                <div class="flex flex-wrap gap-2">
                  <button
                    v-for="language in availableLanguages"
                    :key="language.code"
                    :class="[
                      'px-3 py-2 rounded-lg text-sm font-medium transition-all duration-200',
                      selectedLanguage === language.code
                        ? 'bg-indigo-600 text-white shadow-md'
                        : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600',
                    ]"
                    @click="selectedLanguage = language.code"
                  >
                    <span class="flex items-center">
                      {{ language.name }}
                      <span
                        v-if="!language.hasData"
                        class="ml-1 text-xs opacity-50"
                        >(No data)</span
                      >
                    </span>
                  </button>
                </div>
              </div>

              <!-- Price Data for Selected Language -->
              <div v-if="selectedLanguagePrices.length > 0" class="space-y-4">
                <div class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                  Showing prices for
                  <strong>{{ selectedLanguageData?.name }}</strong>
                </div>

                <!-- Price Cards Grid -->
                <div class="grid gap-3">
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

                <!-- Add to Portfolio button with selected language context -->
                <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                  <div class="flex items-center justify-between">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                      💡 Prices automatically sync from CardTrader marketplace
                    </p>
                    <button
                      class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm rounded-lg transition-colors duration-200 flex items-center"
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
              </div>

              <!-- No data for selected language -->
              <div v-else class="text-center py-8">
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
                    d="M9.172 16.172a4 4 0 015.656 0M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                  />
                </svg>
                <h4
                  class="text-lg font-semibold text-gray-900 dark:text-white mb-2"
                >
                  No Prices for {{ selectedLanguageData?.name }}
                </h4>
                <p class="text-gray-600 dark:text-gray-400 text-sm">
                  Price data is not available for this language yet.
                </p>
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

            <!-- Market Stats (Placeholder) -->
            <div
              class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700"
            >
              <h3
                class="text-lg font-semibold text-gray-900 dark:text-white mb-4"
              >
                Market Statistics
              </h3>
              <div class="space-y-4">
                <div class="flex items-center justify-between">
                  <span class="text-sm text-gray-500 dark:text-gray-400"
                    >Market Cap</span
                  >
                  <span
                    class="text-sm font-semibold text-gray-900 dark:text-white"
                    >Coming Soon</span
                  >
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-sm text-gray-500 dark:text-gray-400"
                    >Avg. Price (30d)</span
                  >
                  <span
                    class="text-sm font-semibold text-gray-900 dark:text-white"
                    >Coming Soon</span
                  >
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-sm text-gray-500 dark:text-gray-400"
                    >Price Change</span
                  >
                  <span
                    class="text-sm font-semibold text-gray-900 dark:text-white"
                    >Coming Soon</span
                  >
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-sm text-gray-500 dark:text-gray-400"
                    >Popularity Rank</span
                  >
                  <span
                    class="text-sm font-semibold text-gray-900 dark:text-white"
                    >Coming Soon</span
                  >
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
              <option v-if="isLoadingPortfolios" value="null" disabled>
                Loading portfolios...
              </option>
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
            <LanguageSelect
              id="language"
              v-model="form.language"
              class="mt-1 block w-full"
            />
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

    <!-- Price History Chart -->
    <div class="py-6">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div
          class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6"
        >
          <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
            Price History
          </h3>
          <div v-if="isLoadingChart" class="text-center">
            <p>Loading chart...</p>
          </div>
          <div v-else-if="priceHistory">
            <PriceHistoryChart
              :chart-data="priceHistory"
              style="height: 300px"
            />
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
