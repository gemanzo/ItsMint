<template>
  <FormModal
    :is-open="isOpen"
    title="Add New Card"
    subtitle="Search for a card and add it to your portfolio"
    size="2xl"
    :is-loading="isSubmitting"
    :can-submit="isFormValid"
    submit-text="Add Card"
    loading-text="Adding..."
    @close="closeModal"
    @submit="submitAddCard"
  >
    <!-- Portfolio Selection -->
    <div>
      <label
        for="portfolio"
        class="block text-sm font-medium text-gray-700 dark:text-gray-300"
      >
        Select Portfolio *
      </label>
      <input
        v-if="props.portfolioId"
        id="portfolio"
        type="text"
        :value="selectedPortfolioName"
        readonly
        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-600 text-gray-900 dark:text-gray-100 shadow-sm sm:text-sm cursor-not-allowed"
      />
      <select
        v-else
        id="portfolio"
        v-model="selectedPortfolioId"
        required
        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:focus:border-indigo-400 dark:focus:ring-indigo-400"
        :class="{
          'border-red-500 focus:border-red-500 focus:ring-red-500':
            formErrors.portfolio,
        }"
      >
        <option disabled value="">Please select one</option>
        <option
          v-for="p in portfolioStore.portfolios"
          :key="p.id"
          :value="p.id"
        >
          {{ p.name }}
        </option>
      </select>
      <p
        v-if="formErrors.portfolio"
        class="mt-1 text-sm text-red-600 dark:text-red-400"
      >
        {{ formErrors.portfolio }}
      </p>
    </div>

    <!-- Blueprint Search -->
    <div>
      <div class="flex items-center justify-between mb-2">
        <label
          for="searchBlueprint"
          class="block text-sm font-medium text-gray-700 dark:text-gray-300"
        >
          Search Card (Blueprint) *
        </label>
        <button
          type="button"
          class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded-md text-indigo-600 bg-indigo-100 hover:bg-indigo-200 dark:bg-indigo-900 dark:text-indigo-200 dark:hover:bg-indigo-800"
          @click="openScanModal"
        >
          <svg
            class="h-4 w-4 mr-1"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke-width="1.5"
            stroke="currentColor"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"
            />
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z"
            />
          </svg>
          Scan Card
        </button>
      </div>

      <div class="relative mt-1 rounded-md shadow-sm">
        <input
          id="searchBlueprint"
          v-model="blueprintSearchQuery"
          type="text"
          placeholder="Type to search... e.g., Luffy OP01-001"
          class="block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:focus:border-indigo-400 dark:focus:ring-indigo-400 pl-3 pr-24"
          autocomplete="off"
          @input="handleSearchInput"
        />
        <div class="absolute inset-y-0 right-0 flex items-center">
          <Listbox v-model="selectedLanguageObject" as="div" class="relative">
            <ListboxButton
              class="relative flex h-full items-center justify-center rounded-md border-0 bg-transparent py-0 pl-2 pr-8 text-gray-500 focus:ring-2 focus:ring-inset focus:ring-indigo-600 dark:text-gray-400 sm:text-sm"
            >
              <span
                v-if="selectedLanguageObject.countryCode"
                :class="['fi', `fi-${selectedLanguageObject.countryCode}`]"
                class="mr-2 h-4 w-6"
              />
              <svg
                v-else
                class="h-5 w-5 text-gray-400 mr-2"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M10.5 21l-5.25-11.25L.75 21m10.5 0H22.5M3.75 12h15M12 12V5.25M15 5.25H9"
                />
              </svg>
              <span class="block truncate">{{
                selectedLanguageObject.name
              }}</span>
              <span
                class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2"
              >
                <svg
                  class="h-5 w-5 text-gray-400"
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 20 20"
                  fill="currentColor"
                  aria-hidden="true"
                >
                  <path
                    fill-rule="evenodd"
                    d="M10 3a.75.75 0 01.53.22l3.5 3.5a.75.75 0 01-1.06 1.06L10 4.81 7.53 7.28a.75.75 0 01-1.06-1.06l3.5-3.5A.75.75 0 0110 3zM10 17a.75.75 0 01-.53-.22l-3.5-3.5a.75.75 0 011.06-1.06L10 15.19l2.47-2.47a.75.75 0 011.06 1.06l-3.5 3.5A.75.75 0 0110 17z"
                    clip-rule="evenodd"
                  />
                </svg>
              </span>
            </ListboxButton>

            <ListboxOptions
              class="absolute z-50 right-0 mt-1 max-h-56 w-40 overflow-auto rounded-md bg-white dark:bg-gray-700 py-1 text-base shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm"
            >
              <ListboxOption
                v-for="lang in CARD_LANGUAGES"
                :key="lang.code"
                v-slot="{ active, selected }"
                :value="lang"
                as="template"
              >
                <li
                  :class="[
                    active
                      ? 'bg-indigo-600 text-white'
                      : 'text-gray-900 dark:text-gray-100',
                    'relative cursor-default select-none py-2 pl-3 pr-9',
                  ]"
                >
                  <div class="flex items-center">
                    <span
                      v-if="lang.countryCode"
                      :class="['fi', `fi-${lang.countryCode}`]"
                      class="mr-3 h-4 w-6"
                    />
                    <svg
                      v-else
                      class="h-5 w-5 text-gray-400 mr-3"
                      xmlns="http://www.w3.org/2000/svg"
                      fill="none"
                      viewBox="0 0 24 24"
                      stroke-width="1.5"
                      stroke="currentColor"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M10.5 21l-5.25-11.25L.75 21m10.5 0H22.5M3.75 12h15M12 12V5.25M15 5.25H9"
                      />
                    </svg>
                    <span
                      :class="[
                        selected ? 'font-semibold' : 'font-normal',
                        'ml-3 block truncate',
                      ]"
                    >
                      {{ lang.name }}
                    </span>
                  </div>

                  <span
                    v-if="selected"
                    :class="[
                      active
                        ? 'text-white'
                        : 'text-indigo-600 dark:text-indigo-400',
                      'absolute inset-y-0 right-0 flex items-center pr-4',
                    ]"
                  >
                    <svg
                      class="h-5 w-5"
                      xmlns="http://www.w3.org/2000/svg"
                      viewBox="0 0 20 20"
                      fill="currentColor"
                      aria-hidden="true"
                    >
                      <path
                        fill-rule="evenodd"
                        d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z"
                        clip-rule="evenodd"
                      />
                    </svg>
                  </span>
                </li>
              </ListboxOption>
            </ListboxOptions>
          </Listbox>
        </div>
      </div>

      <!-- Search Results -->
      <div
        v-if="blueprintSearchResults.length > 0"
        class="absolute z-10 mt-1 w-full bg-white dark:bg-gray-700 shadow-lg max-h-60 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm"
      >
        <ul>
          <li
            v-for="bp in blueprintSearchResults"
            :key="bp.id"
            class="cursor-pointer select-none relative py-2 pl-3 pr-9 hover:bg-indigo-600 hover:text-white group"
            @click="selectBlueprint(bp)"
          >
            <div class="flex items-center justify-between">
              <div class="flex items-center space-x-3 flex-1 min-w-0">
                <img
                  v-if="bp.image_url"
                  :src="bp.image_url"
                  alt="Card image"
                  class="h-12 w-auto object-contain rounded-md flex-shrink-0"
                />
                <span
                  v-else
                  class="h-12 w-8 flex items-center justify-center bg-gray-200 dark:bg-gray-600 rounded-md text-xs text-gray-500 dark:text-gray-400 flex-shrink-0"
                >
                  No Img
                </span>
                <div class="flex-1 min-w-0">
                  <p
                    class="text-sm font-medium truncate group-hover:text-white"
                  >
                    {{ bp.name }}
                  </p>
                  <p
                    class="text-xs text-gray-500 dark:text-gray-400 group-hover:text-indigo-200 truncate"
                  >
                    {{ bp.expansion?.name }} ({{ bp.expansion?.code }})
                    <span v-if="bp.collector_number">
                      - #{{ bp.collector_number }}</span
                    >
                  </p>
                </div>
              </div>

              <div
                v-if="bp.latest_price?.price_cents"
                class="text-right flex-shrink-0 pl-2"
              >
                <p
                  class="font-semibold text-sm text-green-600 dark:text-green-400"
                >
                  {{ formatCurrency(bp.latest_price.price_cents / 100) }}
                </p>
                <p
                  class="text-xs text-gray-500 dark:text-gray-400 group-hover:text-indigo-200"
                >
                  Market
                </p>
              </div>
              <div v-else class="text-right flex-shrink-0 pl-2">
                <p class="text-xs text-gray-400 dark:text-gray-500">No price</p>
              </div>
            </div>
          </li>
          <li
            v-if="hasMoreResults"
            class="p-2 text-center sticky bottom-0 bg-white/80 dark:bg-gray-700/80 backdrop-blur-sm"
          >
            <button
              :disabled="isSearching"
              class="w-full text-indigo-600 dark:text-indigo-400 hover:underline disabled:opacity-50 py-2"
              @click="loadMoreBlueprints"
            >
              {{ isSearching ? 'Loading...' : 'Load More' }}
            </button>
          </li>
        </ul>
      </div>

      <!-- Selected Blueprint Preview -->
      <div
        v-if="selectedBlueprint"
        class="mt-2 p-3 border rounded-md dark:border-gray-600 flex items-center space-x-4"
      >
        <img
          v-if="selectedBlueprint.image_url"
          :src="selectedBlueprint.image_url"
          alt="Selected card image"
          class="h-24 w-auto object-contain rounded-md"
        />
        <span
          v-else
          class="h-24 w-auto flex items-center justify-center bg-gray-200 dark:bg-gray-600 rounded-md text-sm text-gray-500 dark:text-gray-400"
        >
          No Img
        </span>
        <div>
          <p class="text-sm font-semibold dark:text-gray-100">
            Selected: {{ selectedBlueprint.name }}
          </p>
          <p class="text-xs dark:text-gray-300">
            {{ selectedBlueprint.expansion?.name }} ({{
              selectedBlueprint.expansion?.code
            }})
          </p>
        </div>
      </div>

      <p
        v-if="formErrors.blueprint"
        class="mt-1 text-sm text-red-600 dark:text-red-400"
      >
        {{ formErrors.blueprint }}
      </p>
    </div>

    <!-- Condition -->
    <div>
      <label
        for="condition"
        class="block text-sm font-medium text-gray-700 dark:text-gray-300"
      >
        Condition *
      </label>
      <select
        id="condition"
        v-model="cardDetails.condition"
        required
        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:focus:border-indigo-400 dark:focus:ring-indigo-400"
        :class="{
          'border-red-500 focus:border-red-500 focus:ring-red-500':
            formErrors.condition,
        }"
      >
        <option value="NM">Near Mint (NM)</option>
        <option value="LP">Lightly Played (LP)</option>
        <option value="MP">Moderately Played (MP)</option>
        <option value="HP">Heavily Played (HP)</option>
        <option value="DMG">Damaged (DMG)</option>
      </select>
      <p
        v-if="formErrors.condition"
        class="mt-1 text-sm text-red-600 dark:text-red-400"
      >
        {{ formErrors.condition }}
      </p>
    </div>

    <!-- Quantity -->
    <div>
      <label
        for="quantity"
        class="block text-sm font-medium text-gray-700 dark:text-gray-300"
      >
        Quantity *
      </label>
      <input
        id="quantity"
        v-model.number="cardDetails.quantity"
        type="number"
        min="1"
        max="999"
        required
        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:focus:border-indigo-400 dark:focus:ring-indigo-400"
        :class="{
          'border-red-500 focus:border-red-500 focus:ring-red-500':
            formErrors.quantity,
        }"
      />
      <p
        v-if="formErrors.quantity"
        class="mt-1 text-sm text-red-600 dark:text-red-400"
      >
        {{ formErrors.quantity }}
      </p>
    </div>

    <!-- Purchase Price -->
    <PriceInput
      v-model="cardDetails.purchase_price"
      label="Purchase Price"
      :estimated-price="estimatedPrice"
    />

    <!-- Acquired Date -->
    <div>
      <label
        for="acquiredAt"
        class="block text-sm font-medium text-gray-700 dark:text-gray-300"
      >
        Acquired Date
      </label>
      <input
        id="acquiredAt"
        v-model="cardDetails.acquired_at"
        type="date"
        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:focus:border-indigo-400 dark:focus:ring-indigo-400"
      />
    </div>

    <!-- Error Display -->
    <div
      v-if="formErrors.general"
      class="mt-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-md"
    >
      <p class="text-sm text-red-600 dark:text-red-400">
        {{ formErrors.general }}
      </p>
    </div>

    <!-- Scan Modal Integration -->
    <CardScanModal
      v-if="showScanModal"
      :show="showScanModal"
      @close="closeScanModal"
      @scan-complete="handleScanComplete"
    />

    <ScanResultsModal
      v-if="showScanResults && currentScan"
      :show="showScanResults"
      :scan="currentScan"
      @close="closeScanResults"
      @blueprint-selected="handleBlueprintSelected"
    />
  </FormModal>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted, watchEffect } from 'vue';
import {
  Listbox,
  ListboxButton,
  ListboxOption,
  ListboxOptions,
} from '@headlessui/vue';
import { debounce } from 'lodash';
import axios from 'axios';

import { usePortfolioStore, type Blueprint } from '@/stores/portfolioStore';
import { useCardPricing } from '@/composables/useCardPricing';
import { useCurrency } from '@/composables/useCurrency';
import { useApiError } from '@/composables/useApiError';
import { CARD_LANGUAGES } from '@/constants/cardLanguages';

import FormModal from '@/Components/Base/FormModal.vue';
import PriceInput from '@/Components/Forms/PriceInput.vue';
import CardScanModal from '@/Components/CardScanModal.vue';
import ScanResultsModal from '@/Components/ScanResultsModal.vue';

interface Props {
  portfolioId?: number | null;
  portfolioName?: string;
}

interface CardScan {
  id: number;
  status: string;
  created_at: string;
}

const props = defineProps<Props>();
const emit = defineEmits(['close', 'card-added']);

const portfolioStore = usePortfolioStore();
const { formatCurrency } = useCurrency();
const { isLoading: isSubmitting } = useApiError();

const isOpen = ref(true);
const selectedPortfolioId = ref<number | null>(props.portfolioId || null);
const blueprintSearchQuery = ref('');
const blueprintSearchResults = ref<Blueprint[]>([]);
const selectedBlueprint = ref<Blueprint | null>(null);

// Initialize with EN language (index 1) instead of Any (index 0)
const selectedLanguageObject = ref(
  CARD_LANGUAGES.find((lang) => lang.code === 'en') || CARD_LANGUAGES[1]
);
const searchLanguage = computed(() => selectedLanguageObject.value.code);

const searchPage = ref(1);
const hasMoreResults = ref(false);
const isSearching = ref(false);

// Scan modal states
const showScanModal = ref(false);
const showScanResults = ref(false);
const currentScan = ref<CardScan | null>(null);

const cardDetails = reactive({
  condition: 'NM',
  language: 'EN',
  quantity: 1,
  purchase_price: null as number | null,
  acquired_at: undefined as string | undefined,
});

const formErrors = reactive<Record<string, string>>({});

// Use pricing composable
const { estimatedPrice } = useCardPricing(
  selectedBlueprint,
  computed(() => cardDetails.condition)
);

// Computed property to get the selected portfolio name
const selectedPortfolioName = computed(() => {
  if (!selectedPortfolioId.value) return '';

  if (props.portfolioName && props.portfolioId === selectedPortfolioId.value) {
    return props.portfolioName;
  }

  const portfolio = portfolioStore.portfolios.find(
    (p) => p.id === selectedPortfolioId.value
  );
  return portfolio?.name || '';
});

// Form validation
const isFormValid = computed(() => {
  return (
    selectedPortfolioId.value !== null &&
    selectedBlueprint.value !== null &&
    !!cardDetails.condition &&
    cardDetails.quantity >= 1 &&
    cardDetails.quantity <= 999
  );
});

watch(
  () => props.portfolioId,
  (newVal) => {
    selectedPortfolioId.value = newVal || null;
  }
);

const closeModal = () => {
  isOpen.value = false;
  setTimeout(() => emit('close'), 300);
};

const searchBlueprints = async (loadMore = false) => {
  if (blueprintSearchQuery.value.length < 2) {
    blueprintSearchResults.value = [];
    return;
  }

  if (!loadMore) {
    searchPage.value = 1;
    blueprintSearchResults.value = [];
  }

  isSearching.value = true;

  try {
    const response = await axios.get('/blueprints/search', {
      params: {
        q: blueprintSearchQuery.value,
        from: (searchPage.value - 1) * 15,
        size: 15,
        language:
          searchLanguage.value && searchLanguage.value !== ''
            ? searchLanguage.value
            : undefined,
      },
    });

    if (response.data.success && response.data.data) {
      if (loadMore) {
        blueprintSearchResults.value.push(...response.data.data);
      } else {
        blueprintSearchResults.value = response.data.data;
      }

      hasMoreResults.value = response.data.data.length === 15;

      if (loadMore) {
        searchPage.value++;
      }
    }
  } catch (error) {
    console.error('Error searching blueprints:', error);
    blueprintSearchResults.value = [];
  } finally {
    isSearching.value = false;
  }
};

const debouncedSearchBlueprints = debounce(searchBlueprints, 300);

const handleSearchInput = () => {
  debouncedSearchBlueprints();
};

const loadMoreBlueprints = () => {
  searchBlueprints(true);
};

const selectBlueprint = (blueprint: Blueprint) => {
  selectedBlueprint.value = blueprint;
  blueprintSearchQuery.value = `${blueprint.name} - ${blueprint.expansion?.code}`;
  blueprintSearchResults.value = [];

  // Auto-set language based on selection context
  if (blueprint.latest_price?.language) {
    cardDetails.language = blueprint.latest_price.language.toUpperCase();
  } else if (searchLanguage.value) {
    cardDetails.language = searchLanguage.value.toUpperCase();
  } else {
    cardDetails.language = 'EN';
  }
};

const validateForm = (): boolean => {
  Object.keys(formErrors).forEach((key) => delete formErrors[key]);
  let isValid = true;

  if (!selectedPortfolioId.value) {
    formErrors.portfolio = 'Portfolio selection is required.';
    isValid = false;
  }
  if (!selectedBlueprint.value) {
    formErrors.blueprint = 'Card selection is required.';
    isValid = false;
  }
  if (!cardDetails.condition) {
    formErrors.condition = 'Condition is required.';
    isValid = false;
  }
  if (!cardDetails.quantity || cardDetails.quantity < 1) {
    formErrors.quantity = 'Quantity must be at least 1.';
    isValid = false;
  }
  return isValid;
};

const submitAddCard = async () => {
  if (!validateForm() || !isFormValid.value) return;

  isSubmitting.value = true;

  try {
    const response = await portfolioStore.createUserCard({
      blueprint_id: selectedBlueprint.value!.id,
      condition: cardDetails.condition,
      language: cardDetails.language,
      quantity: cardDetails.quantity,
      purchase_price: cardDetails.purchase_price,
      acquired_at: cardDetails.acquired_at,
    });

    const createdCards = Array.isArray(response) ? response : [response];

    if (createdCards.length > 0 && selectedPortfolioId.value) {
      for (const userCard of createdCards) {
        await portfolioStore.addUserCardToPortfolio(
          selectedPortfolioId.value,
          userCard.id,
          1
        );
      }

      emit('card-added', {
        portfolioId: selectedPortfolioId.value,
        cards: createdCards,
        totalQuantity: cardDetails.quantity,
      });
      closeModal();
    } else {
      throw new Error(
        'Failed to create cards or associate them with portfolio.'
      );
    }
  } catch (error: unknown) {
    console.error('Error adding card:', error);
    const errorMessage =
      error instanceof Error ? error.message : 'An unknown error occurred.';
    const axiosError = error as { response?: { data?: { message?: string } } };
    formErrors.general = axiosError.response?.data?.message || errorMessage;
  } finally {
    isSubmitting.value = false;
  }
};

// Scan modal methods
const openScanModal = () => {
  showScanModal.value = true;
};

const closeScanModal = () => {
  showScanModal.value = false;
};

const handleScanComplete = (scan: CardScan) => {
  currentScan.value = scan;
  showScanModal.value = false;
  showScanResults.value = true;
};

const closeScanResults = () => {
  showScanResults.value = false;
  currentScan.value = null;
};

const handleBlueprintSelected = (blueprint: Blueprint) => {
  selectBlueprint(blueprint);
  closeScanResults();
};

// Watch for search query changes - reset selected blueprint when query is cleared
watch(blueprintSearchQuery, (newQuery) => {
  if (!newQuery || newQuery.trim() === '') {
    selectedBlueprint.value = null;
  }
});

// Watch for language changes - restart search if there's an active query
watch(selectedLanguageObject, () => {
  if (blueprintSearchQuery.value && blueprintSearchQuery.value.length >= 2) {
    debouncedSearchBlueprints();
  }
});

// Sync language selection with card details
watchEffect(() => {
  cardDetails.language =
    selectedLanguageObject.value.code.toUpperCase() || 'EN';
});

onMounted(async () => {
  if (portfolioStore.portfolios.length === 0) {
    await portfolioStore.fetchPortfolios();
  }

  if (!props.portfolioId && portfolioStore.portfolios.length === 1) {
    selectedPortfolioId.value = portfolioStore.portfolios[0].id;
  }
});
</script>
