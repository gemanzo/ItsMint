<template>
    <TransitionRoot appear :show="isOpen" as="template">
        <Dialog as="div" @close="closeModal" class="relative z-10">
            <TransitionChild
                as="template"
                enter="duration-300 ease-out"
                enter-from="opacity-0"
                enter-to="opacity-100"
                leave="duration-200 ease-in"
                leave-from="opacity-100"
                leave-to="opacity-0"
            >
                <div class="fixed inset-0 bg-black bg-opacity-25" />
            </TransitionChild>

            <div class="fixed inset-0 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4 text-center">
                    <TransitionChild
                        as="template"
                        enter="duration-300 ease-out"
                        enter-from="opacity-0 scale-95"
                        enter-to="opacity-100 scale-100"
                        leave="duration-200 ease-in"
                        leave-from="opacity-100 scale-100"
                        leave-to="opacity-0 scale-95"
                    >
                        <DialogPanel class="w-full max-w-2xl transform overflow-hidden rounded-2xl bg-white dark:bg-gray-800 p-6 text-left align-middle shadow-xl transition-all">
                            <DialogTitle as="h3" class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100">
                                Add New Card
                            </DialogTitle>
                            <form @submit.prevent="submitAddCard">
                                <div class="mt-4 space-y-4">
                                    <div>
                                        <label for="portfolio" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Select Portfolio</label>
                                        <input 
                                            v-if="props.portfolioId"
                                            type="text"
                                            id="portfolio"
                                            :value="selectedPortfolioName"
                                            readonly
                                            class="mt-1 block w-full rounded-md border-gray-300 bg-gray-50 dark:bg-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:border-gray-600 dark:text-gray-200 cursor-not-allowed"
                                        />
                                        <select 
                                            v-else
                                            id="portfolio"
                                            v-model="selectedPortfolioId"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"
                                        >
                                            <option disabled value="">Please select one</option>
                                            <option v-for="p in portfolioStore.portfolios" :key="p.id" :value="p.id">{{ p.name }}</option>
                                        </select>
                                        <p v-if="!selectedPortfolioId && formErrors.portfolio" class="text-xs text-red-500 mt-1">{{ formErrors.portfolio }}</p>
                                    </div>

                                    <div>
                                        <div class="flex items-center justify-between mb-2">
                                            <label for="searchBlueprint" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Search Card (Blueprint)</label>
                                            <button 
                                                type="button"
                                                @click="openScanModal"
                                                class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded-md text-indigo-600 bg-indigo-100 hover:bg-indigo-200 dark:bg-indigo-900 dark:text-indigo-200 dark:hover:bg-indigo-800"
                                            >
                                                <svg class="h-4 w-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
                                                </svg>
                                                Scan Card
                                            </button>
                                        </div>
                                        <div class="relative mt-1 rounded-md shadow-sm">
                                            <input 
                                                type="text" 
                                                id="searchBlueprint" 
                                                v-model="blueprintSearchQuery"
                                                @input="handleSearchInput"
                                                placeholder="Type to search... e.g., Luffy OP01-001"
                                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 pl-3 pr-24"
                                                autocomplete="off"
                                            />
                                            <div class="absolute inset-y-0 right-0 flex items-center">
                                                <Listbox v-model="selectedLanguageObject" as="div" class="relative">
                                                    <ListboxButton class="relative flex h-full items-center justify-center rounded-md border-0 bg-transparent py-0 pl-2 pr-8 text-gray-500 focus:ring-2 focus:ring-inset focus:ring-indigo-600 dark:text-gray-400 sm:text-sm">
                                                        <span v-if="selectedLanguageObject.countryCode" :class="['fi', `fi-${selectedLanguageObject.countryCode}`]" class="mr-2 h-4 w-6"></span>
                                                        <svg v-else class="h-5 w-5 text-gray-400 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 21l-5.25-11.25L.75 21m10.5 0H22.5M3.75 12h15M12 12V5.25M15 5.25H9" />
                                                        </svg>
                                                        <span class="block truncate">{{ selectedLanguageObject.name }}</span>
                                                        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
                                                            <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                                <path fill-rule="evenodd" d="M10 3a.75.75 0 01.53.22l3.5 3.5a.75.75 0 01-1.06 1.06L10 4.81 7.53 7.28a.75.75 0 01-1.06-1.06l3.5-3.5A.75.75 0 0110 3zM10 17a.75.75 0 01-.53-.22l-3.5-3.5a.75.75 0 011.06-1.06L10 15.19l2.47-2.47a.75.75 0 011.06 1.06l-3.5 3.5A.75.75 0 0110 17z" clip-rule="evenodd" />
                                                            </svg>
                                                        </span>
                                                    </ListboxButton>
                                                    <transition leave-active-class="transition ease-in duration-100" leave-from-class="opacity-100" leave-to-class="opacity-0">
                                                        <ListboxOptions class="absolute right-0 z-20 mt-1 w-32 origin-top-right overflow-hidden rounded-md bg-white dark:bg-gray-800 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
                                                            <ListboxOption
                                                                v-for="lang in languages"
                                                                :key="lang.code"
                                                                :value="lang"
                                                                as="template"
                                                                v-slot="{ active, selected }"
                                                            >
                                                                <li :class="[active ? 'bg-indigo-600 text-white' : 'text-gray-900 dark:text-gray-200', 'relative cursor-default select-none py-2 pl-3 pr-9']">
                                                                    <div class="flex items-center">
                                                                        <span v-if="lang.countryCode" :class="['fi', `fi-${lang.countryCode}`]" class="mr-2 h-4 w-6"></span>
                                                                        <svg v-else class="h-5 w-5 text-gray-400 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 21l-5.25-11.25L.75 21m10.5 0H22.5M3.75 12h15M12 12V5.25M15 5.25H9" />
                                                                        </svg>
                                                                        <span :class="[selected ? 'font-semibold' : 'font-normal', 'block truncate']">{{ lang.name }}</span>
                                                                    </div>
                                                                </li>
                                                            </ListboxOption>
                                                        </ListboxOptions>
                                                    </transition>
                                                </Listbox>
                                            </div>
                                        </div>
                                        <div class="relative">
                                            <div v-if="blueprintSearchQuery.length > 1 && !selectedBlueprint" class="absolute z-10 mt-1 w-full max-h-72 overflow-auto rounded-md bg-white dark:bg-gray-700 text-base shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">
                                                <!-- Loading State -->
                                                <div v-if="isSearching && blueprintSearchResults.length === 0" class="p-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                                    Searching for cards...
                                                </div>

                                                <!-- No Results -->
                                                <div v-else-if="!isSearching && blueprintSearchResults.length === 0" class="p-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                                    No cards found for "{{ blueprintSearchQuery }}".
                                                </div>

                                                <!-- Search Results -->
                                                <ul v-else class="py-1">
                                                    <li
                                                        v-for="bp in blueprintSearchResults"
                                                        :key="bp.id"
                                                        @click="selectBlueprint(bp)"
                                                        class="group cursor-pointer select-none relative p-3 text-gray-900 dark:text-gray-200 hover:bg-indigo-600 hover:text-white flex items-center space-x-3 transition-colors duration-150"
                                                    >
                                                        <img v-if="bp.image_url" :src="bp.image_url" alt="Card image" class="h-20 w-auto flex-shrink-0 object-contain rounded-sm" />
                                                        <span v-else class="h-20 w-14 flex-shrink-0 flex items-center justify-center bg-gray-200 dark:bg-gray-600 rounded-sm text-xs text-gray-500 dark:text-gray-400">No Img</span>
                                                        
                                                        <div class="flex-1 min-w-0">
                                                            <p class="font-medium truncate" :title="bp.name">{{ bp.name }}</p>
                                                            <p class="text-xs text-gray-500 dark:text-gray-400 group-hover:text-indigo-200 truncate" :title="`${bp.expansion?.name} (${bp.expansion?.code})`">{{ bp.expansion?.name }} ({{ bp.expansion?.code }})</p>
                                                            <span v-if="bp.latest_price?.is_foil" class="text-xs text-yellow-500 font-bold">✨ Foil</span>
                                                        </div>

                                                        <div v-if="bp.latest_price?.price_cents" class="text-right flex-shrink-0 pl-2">
                                                            <p class="font-semibold text-sm text-green-600 dark:text-green-400">
                                                                {{ formatCurrency(bp.latest_price.price_cents / 100) }}
                                                            </p>
                                                            <p class="text-xs text-gray-500 dark:text-gray-400 group-hover:text-indigo-200">Market</p>
                                                        </div>
                                                        <div v-else class="text-right flex-shrink-0 pl-2">
                                                            <p class="text-xs text-gray-400 dark:text-gray-500">No price</p>
                                                        </div>
                                                    </li>
                                                    <li v-if="hasMoreResults" class="p-2 text-center sticky bottom-0 bg-white/80 dark:bg-gray-700/80 backdrop-blur-sm">
                                                        <button @click="loadMoreBlueprints" :disabled="isSearching" class="w-full text-indigo-600 dark:text-indigo-400 hover:underline disabled:opacity-50 py-2">
                                                            {{ isSearching ? 'Loading...' : 'Load More' }}
                                                        </button>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                        <div v-if="selectedBlueprint" class="mt-2 p-3 border rounded-md dark:border-gray-600 flex items-center space-x-4">
                                            <img v-if="selectedBlueprint.image_url" :src="selectedBlueprint.image_url" alt="Selected card image" class="h-24 w-auto object-contain rounded-md" />
                                            <span v-else class="h-24 w-auto flex items-center justify-center bg-gray-200 dark:bg-gray-600 rounded-md text-sm text-gray-500 dark:text-gray-400">No Img</span>
                                            <div>
                                                <p class="text-sm font-semibold dark:text-gray-100">Selected: {{ selectedBlueprint.name }}</p>
                                                <p class="text-xs dark:text-gray-300">{{ selectedBlueprint.expansion?.name }} ({{ selectedBlueprint.expansion?.code }})</p>
                                            </div>
                                        </div>
                                        <p v-if="!selectedBlueprint && formErrors.blueprint" class="text-xs text-red-500 mt-1">{{ formErrors.blueprint }}</p>
                                    </div>

                                    <div>
                                        <label for="condition" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Condition</label>
                                        <select id="condition" v-model="cardDetails.condition" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                                            <option value="NM">Near Mint (NM)</option>
                                            <option value="LP">Lightly Played (LP)</option>
                                            <option value="MP">Moderately Played (MP)</option>
                                            <option value="HP">Heavily Played (HP)</option>
                                            <option value="DMG">Damaged (DMG)</option>
                                        </select>
                                         <p v-if="!cardDetails.condition && formErrors.condition" class="text-xs text-red-500 mt-1">{{ formErrors.condition }}</p>
                                    </div>
                                    
                                    <div>
                                        <label for="quantity" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Quantity</label>
                                        <input type="number" id="quantity" v-model.number="cardDetails.quantity" min="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200" />
                                        <p v-if="!cardDetails.quantity && formErrors.quantity" class="text-xs text-red-500 mt-1">{{ formErrors.quantity }}</p>
                                    </div>

                                    <div>
                                        <label for="acquiredAt" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Acquired Date (Optional)</label>
                                        <input type="date" id="acquiredAt" v-model="cardDetails.acquired_at" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200" />
                                    </div>

                                    <div>
                                        <label for="purchasePrice" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Purchase Price
                                            <span class="text-xs text-gray-500 dark:text-gray-400 font-normal">- Leave empty to use estimated value</span>
                                        </label>
                                        <div class="relative">
                                            <input type="number" id="purchasePrice" v-model.number="cardDetails.purchase_price" step="0.01" placeholder="e.g., 10.50" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 pr-10" />
                                            <span class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-gray-500 dark:text-gray-400 text-sm">€</span>
                                        </div>
                                        <p v-if="estimatedPrice && !cardDetails.purchase_price" class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                                            💡 Will use estimated value: {{ formatCurrency(estimatedPrice) }}
                                        </p>
                                    </div>

                                    <!-- Notes, Acquired At, etc. can be added here -->

                                </div>

                                <div class="mt-6 flex justify-end space-x-3">
                                    <button type="button" @click="closeModal" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">
                                        Cancel
                                    </button>
                                    <button type="submit" :disabled="isSubmitting" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 disabled:opacity-50">
                                        {{ isSubmitting ? 'Adding...' : 'Add Card' }}
                                    </button>
                                </div>
                                 <p v-if="formErrors.general" class="text-xs text-red-500 mt-2 text-right">{{ formErrors.general }}</p>
                            </form>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>

    <!-- Scan Modal -->
    <CardScanModal 
        :show="showScanModal"
        @close="closeScanModal"
        @scan-complete="handleScanComplete"
    />

    <!-- Scan Results Modal -->
    <ScanResultsModal 
        :show="showScanResults"
        :scan="currentScan"
        @close="closeScanResults"
        @card-created="handleCardCreated"
    />
</template>

<script setup lang="ts">
import { ref, reactive, watch, onMounted, computed } from 'vue';
import "/node_modules/flag-icons/css/flag-icons.min.css";
import {
    Dialog,
    DialogPanel,
    DialogTitle,
    TransitionRoot,
    TransitionChild,
    Listbox,
    ListboxButton,
    ListboxOptions,
    ListboxOption,
} from '@headlessui/vue';
import axios from 'axios';
import { usePortfolioStore, Blueprint, UserCard } from '@/stores/portfolioStore';
import debounce from 'lodash/debounce';
import CardScanModal from '@/Components/CardScanModal.vue';
import ScanResultsModal from '@/Components/ScanResultsModal.vue';

const props = defineProps<{ 
    portfolioId?: number | null;
    portfolioName?: string | null;
}>();

const emit = defineEmits(['close', 'card-added']);

const portfolioStore = usePortfolioStore();

const isOpen = ref(true); // Modal is open by default when component is rendered
const selectedPortfolioId = ref<number | null>(props.portfolioId || null);
const blueprintSearchQuery = ref('');
const blueprintSearchResults = ref<Blueprint[]>([]);
const selectedBlueprint = ref<Blueprint | null>(null);

const languages = [
    { name: 'Any', code: '', countryCode: 'un' },
    { name: 'EN', code: 'en', countryCode: 'us' },
    { name: 'JP', code: 'jp', countryCode: 'jp' },
    { name: 'FR', code: 'fr', countryCode: 'fr' },
    { name: 'CHI', code: 'zh-cn', countryCode: 'cn' },
];

const selectedLanguageObject = ref(languages[0]);
const searchLanguage = computed(() => selectedLanguageObject.value.code);

const isSubmitting = ref(false);
const searchPage = ref(1);
const hasMoreResults = ref(false);
const isSearching = ref(false);

// Scan modal states
const showScanModal = ref(false);
const showScanResults = ref(false);
const currentScan = ref(null);

const cardDetails = reactive({
    condition: 'NM',
    language: 'EN',
    quantity: 1,
    purchase_price: null as number | null,
    acquired_at: undefined as string | undefined,
    // notes: ''
});

const formErrors = reactive<Record<string, string>>({});

// Computed property to get the selected portfolio name
const selectedPortfolioName = computed(() => {
    if (!selectedPortfolioId.value) return '';
    
    // If portfolioName is provided as prop, use it (for when opened from specific portfolio page)
    if (props.portfolioName && props.portfolioId === selectedPortfolioId.value) {
        return props.portfolioName;
    }
    
    // Otherwise, try to find it in the store
    const portfolio = portfolioStore.portfolios.find(p => p.id === selectedPortfolioId.value);
    return portfolio?.name || '';
});

watch(() => props.portfolioId, (newVal) => {
    selectedPortfolioId.value = newVal || null;
});

const closeModal = () => {
    isOpen.value = false;
    setTimeout(() => emit('close'), 300); // Allow time for transition
};

const estimatedPrice = computed(() => {
    if (selectedBlueprint.value?.latest_price?.price_cents) {
        return selectedBlueprint.value.latest_price.price_cents / 100;
    }
    return null;
});

const formatCurrency = (value: number | null | undefined, currency = 'EUR') => {
    if (value === null || typeof value === 'undefined') return 'N/A';
    return new Intl.NumberFormat('it-IT', { style: 'currency', currency: currency }).format(value);
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
        // Use Elasticsearch API - backend now handles normalization and fuzzy matching
        const response = await axios.get('/blueprints/search', {
            params: { 
                q: blueprintSearchQuery.value,
                from: (searchPage.value - 1) * 15,
                size: 15, // Increase size for better experience
                language: searchLanguage.value || undefined,
            }
        });
        
        // Handle Elasticsearch response format
        if (response.data.success && response.data.data) {
            if (loadMore) {
                blueprintSearchResults.value.push(...response.data.data);
            } else {
                blueprintSearchResults.value = response.data.data;
            }
            hasMoreResults.value = response.data.pagination.has_more;
        } else {
            blueprintSearchResults.value = [];
            hasMoreResults.value = false;
        }
    } catch (error) {
        console.error('Error searching blueprints:', error);
        blueprintSearchResults.value = [];
        hasMoreResults.value = false;
    } finally {
        isSearching.value = false;
    }
};

const loadMoreBlueprints = () => {
    if (hasMoreResults.value && !isSearching.value) {
        searchPage.value++;
        searchBlueprints(true);
    }
};

// Watch for language changes to re-trigger search
watch(searchLanguage, () => {
    if (blueprintSearchQuery.value.length >= 2) {
        searchBlueprints(false); // Trigger a new search
    }
});

// Watch for changes in search query to auto-clear selected blueprint
watch(blueprintSearchQuery, (newValue, oldValue) => {
    // If user clears or significantly changes the search, deselect blueprint
    if (selectedBlueprint.value && newValue.length < 2) {
        selectedBlueprint.value = null;
        blueprintSearchResults.value = [];
    }
    // If user types something different after selecting, show search again
    else if (selectedBlueprint.value && newValue !== `${selectedBlueprint.value.name} - ${selectedBlueprint.value.expansion?.code}`) {
        // Don't auto-deselect immediately, but allow search to show
        // Only deselect if query becomes too different from selected blueprint
        const selectedBlueprintText = `${selectedBlueprint.value.name} - ${selectedBlueprint.value.expansion?.code}`;
        if (!selectedBlueprintText.toLowerCase().includes(newValue.toLowerCase()) && newValue.length > 2) {
            selectedBlueprint.value = null;
        }
    }
});

const debouncedSearchBlueprints = debounce(searchBlueprints, 300);

// Wrapper for input event handler
const handleSearchInput = () => {
    debouncedSearchBlueprints();
};

const selectBlueprint = (blueprint: Blueprint) => {
    selectedBlueprint.value = blueprint;
    blueprintSearchQuery.value = `${blueprint.name} - ${blueprint.expansion?.code}`;
    blueprintSearchResults.value = []; // Clear results

    // Auto-set language based on selection context
    if (blueprint.latest_price?.language) {
        cardDetails.language = blueprint.latest_price.language.toUpperCase();
    } else if (searchLanguage.value) { // from the search filter
        cardDetails.language = searchLanguage.value.toUpperCase();
    } else {
        cardDetails.language = 'EN'; // Fallback default
    }
};

const validateForm = (): boolean => {
    Object.keys(formErrors).forEach(key => delete formErrors[key]); // Clear previous errors
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
    if (!validateForm()) return;

    isSubmitting.value = true;
    formErrors.general = '';

    try {
        // Single API call that creates multiple UserCard records
        const response = await portfolioStore.createUserCard({
            blueprint_id: selectedBlueprint.value!.id,
            condition: cardDetails.condition,
            language: cardDetails.language,
            quantity: cardDetails.quantity,
            purchase_price: cardDetails.purchase_price,
            acquired_at: cardDetails.acquired_at,
            // notes: cardDetails.notes,
        });

        // Response can be single UserCard or array of UserCards
        const createdCards = Array.isArray(response) ? response : [response];

        if (createdCards.length > 0 && selectedPortfolioId.value) {
            // Add all cards to portfolio in a single operation
            for (const userCard of createdCards) {
                await portfolioStore.addUserCardToPortfolio(
                    selectedPortfolioId.value,
                    userCard.id,
                    1 // Each card gets quantity 1
                );
            }

            emit('card-added', { 
                portfolioId: selectedPortfolioId.value, 
                cards: createdCards,
                totalQuantity: cardDetails.quantity 
            });
            closeModal();
        } else {
            throw new Error('Failed to create cards or associate them with portfolio.');
        }

    } catch (error: any) {
        console.error('Error adding card:', error);
        formErrors.general = error.response?.data?.message || error.message || 'An unknown error occurred.';
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

const handleScanComplete = (scan: any) => {
    currentScan.value = scan;
    showScanModal.value = false;
    showScanResults.value = true;
};

const closeScanResults = () => {
    showScanResults.value = false;
    currentScan.value = null;
};

const handleCardCreated = (scan: any) => {
    // If scan has an assigned blueprint, auto-select it
    if (scan.blueprint) {
        selectBlueprint(scan.blueprint);
    }
    closeScanResults();
};

onMounted(async () => {
    // If portfolios are not loaded yet, fetch them
    if (portfolioStore.portfolios.length === 0) {
        await portfolioStore.fetchPortfolios();
    }
    
    // If we have a specific portfolioId, also fetch its details to ensure it's in the store
    if (props.portfolioId) {
        await portfolioStore.fetchPortfolioDetails(props.portfolioId);
    }
});

</script> 