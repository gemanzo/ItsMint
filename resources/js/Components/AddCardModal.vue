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
                                        <select 
                                            id="portfolio"
                                            v-model="selectedPortfolioId"
                                            :disabled="!!props.portfolioId" 
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"
                                        >
                                            <option disabled value="">Please select one</option>
                                            <option v-for="p in portfolioStore.portfolios" :key="p.id" :value="p.id">{{ p.name }}</option>
                                        </select>
                                        <p v-if="!selectedPortfolioId && formErrors.portfolio" class="text-xs text-red-500 mt-1">{{ formErrors.portfolio }}</p>
                                    </div>

                                    <div>
                                        <label for="searchBlueprint" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Search Card (Blueprint)</label>
                                        <div class="relative">
                                            <input 
                                                type="text" 
                                                id="searchBlueprint" 
                                                v-model="blueprintSearchQuery"
                                                @input="debouncedSearchBlueprints"
                                                placeholder="Type to search... e.g., Luffy OP01-001"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"
                                            />
                                            <ul v-if="blueprintSearchResults.length > 0 && blueprintSearchQuery.length > 1 && !selectedBlueprint" class="absolute z-10 mt-1 max-h-72 w-full overflow-auto rounded-md bg-white dark:bg-gray-700 py-1 text-base shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">
                                                <li 
                                                    v-for="bp in blueprintSearchResults" 
                                                    :key="bp.id" 
                                                    @click="selectBlueprint(bp)"
                                                    class="cursor-pointer select-none relative p-2 text-gray-900 dark:text-gray-200 hover:bg-indigo-600 hover:text-white flex items-center space-x-3"
                                                >
                                                    <img v-if="bp.image_url" :src="bp.image_url" alt="Card image" class="h-16 w-auto object-contain rounded-sm" />
                                                    <span v-else class="h-16 w-12 flex items-center justify-center bg-gray-200 dark:bg-gray-600 rounded-sm text-xs text-gray-500 dark:text-gray-400">No Img</span>
                                                    <div>
                                                        <p class="font-medium">{{ bp.name }}</p>
                                                        <p class="text-xs">{{ bp.expansion?.name }} ({{ bp.expansion?.code }})</p>
                                                    </div>
                                                </li>
                                            </ul>
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
                                        <label for="language" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Language</label>
                                        <input type="text" id="language" v-model="cardDetails.language" placeholder="e.g., EN, JP, IT" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200" />
                                        <p v-if="!cardDetails.language && formErrors.language" class="text-xs text-red-500 mt-1">{{ formErrors.language }}</p>
                                    </div>

                                    <div>
                                        <label for="quantity" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Quantity</label>
                                        <input type="number" id="quantity" v-model.number="cardDetails.quantity" min="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200" />
                                        <p v-if="!cardDetails.quantity && formErrors.quantity" class="text-xs text-red-500 mt-1">{{ formErrors.quantity }}</p>
                                    </div>

                                    <div>
                                        <label for="purchasePrice" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Purchase Price (Optional)</label>
                                        <input type="number" id="purchasePrice" v-model.number="cardDetails.purchase_price" step="0.01" placeholder="e.g., 10.50" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200" />
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
</template>

<script setup lang="ts">
import { ref, reactive, watch, onMounted } from 'vue';
import {
    Dialog,
    DialogPanel,
    DialogTitle,
    TransitionRoot,
    TransitionChild,
} from '@headlessui/vue';
import axios from 'axios';
import { usePortfolioStore, Blueprint, UserCard } from '@/stores/portfolioStore';
import debounce from 'lodash/debounce';

const props = defineProps<{ 
    portfolioId?: number | null 
}>();

const emit = defineEmits(['close', 'card-added']);

const portfolioStore = usePortfolioStore();

const isOpen = ref(true); // Modal is open by default when component is rendered
const selectedPortfolioId = ref<number | null>(props.portfolioId || null);
const blueprintSearchQuery = ref('');
const blueprintSearchResults = ref<Blueprint[]>([]);
const selectedBlueprint = ref<Blueprint | null>(null);
const isSubmitting = ref(false);

const cardDetails = reactive({
    condition: 'NM',
    language: 'EN',
    quantity: 1,
    purchase_price: null as number | null,
    // notes: ''
});

const formErrors = reactive<Record<string, string>>({});

watch(() => props.portfolioId, (newVal) => {
    selectedPortfolioId.value = newVal || null;
});

const closeModal = () => {
    isOpen.value = false;
    setTimeout(() => emit('close'), 300); // Allow time for transition
};

const searchBlueprints = async () => {
    if (blueprintSearchQuery.value.length < 2) {
        blueprintSearchResults.value = [];
        return;
    }
    try {
        const response = await axios.get<Blueprint[]>('/api/blueprints/search', {
            params: { query: blueprintSearchQuery.value }
        });
        blueprintSearchResults.value = response.data;
    } catch (error) {
        console.error('Error searching blueprints:', error);
        blueprintSearchResults.value = [];
    }
};

const debouncedSearchBlueprints = debounce(searchBlueprints, 300);

const selectBlueprint = (blueprint: Blueprint) => {
    selectedBlueprint.value = blueprint;
    blueprintSearchQuery.value = `${blueprint.name} - ${blueprint.expansion?.code}`;
    blueprintSearchResults.value = []; // Clear results
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
    if (!cardDetails.language) {
        formErrors.language = 'Language is required.';
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
        // 1. Create the UserCard
        const newUserCard = await portfolioStore.createUserCard({
            blueprint_id: selectedBlueprint.value!.id,
            condition: cardDetails.condition,
            language: cardDetails.language,
            purchase_price: cardDetails.purchase_price === null ? undefined : cardDetails.purchase_price,
            // notes: cardDetails.notes,
        });

        // 2. Add UserCard to the selected Portfolio
        if (selectedPortfolioId.value && newUserCard) {
            await portfolioStore.addUserCardToPortfolio(
                selectedPortfolioId.value,
                newUserCard.id,
                cardDetails.quantity
            );
            emit('card-added', { portfolioId: selectedPortfolioId.value, card: newUserCard });
            closeModal();
        } else {
            throw new Error('Failed to associate card with portfolio.');
        }

    } catch (error: any) {
        console.error('Error adding card:', error);
        formErrors.general = error.response?.data?.message || error.message || 'An unknown error occurred.';
    } finally {
        isSubmitting.value = false;
    }
};

onMounted(() => {
    // If portfolios are not loaded yet, fetch them
    if (portfolioStore.portfolios.length === 0) {
        portfolioStore.fetchPortfolios();
    }
});

</script> 