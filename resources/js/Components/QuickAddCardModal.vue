<template>
  <TransitionRoot appear :show="isOpen" as="template">
    <Dialog as="div" class="relative z-10" @close="closeModal">
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
        <div
          class="flex min-h-full items-center justify-center p-4 text-center"
        >
          <TransitionChild
            as="template"
            enter="duration-300 ease-out"
            enter-from="opacity-0 scale-95"
            enter-to="opacity-100 scale-100"
            leave="duration-200 ease-in"
            leave-from="opacity-100 scale-100"
            leave-to="opacity-0 scale-95"
          >
            <DialogPanel
              class="w-full max-w-lg transform overflow-hidden rounded-2xl bg-white dark:bg-gray-800 p-6 text-left align-middle shadow-xl transition-all"
            >
              <DialogTitle
                as="h3"
                class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100"
              >
                Add Card to Portfolio
              </DialogTitle>

              <!-- Card Preview -->
              <div
                class="mt-4 p-3 border rounded-md dark:border-gray-600 flex items-center space-x-4"
              >
                <img
                  v-if="props.blueprint.image_url"
                  :src="props.blueprint.image_url"
                  alt="Card image"
                  class="h-20 w-auto object-contain rounded-md"
                />
                <span
                  v-else
                  class="h-20 w-14 flex items-center justify-center bg-gray-200 dark:bg-gray-600 rounded-md text-sm text-gray-500 dark:text-gray-400"
                >
                  No Img
                </span>
                <div>
                  <p class="text-sm font-semibold dark:text-gray-100">
                    {{ props.blueprint.name }}
                  </p>
                  <p class="text-xs dark:text-gray-300">
                    {{ props.blueprint.expansion?.name }} ({{
                      props.blueprint.expansion?.code
                    }})
                  </p>
                  <p
                    v-if="props.blueprint.collector_number"
                    class="text-xs text-gray-400 dark:text-gray-500 font-mono"
                  >
                    #{{ props.blueprint.collector_number }}
                  </p>
                </div>
              </div>

              <form @submit.prevent="submitAddCard">
                <div class="mt-4 space-y-4">
                  <!-- Portfolio Selection -->
                  <div>
                    <label
                      for="portfolio"
                      class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                      Select Portfolio
                    </label>
                    <select
                      id="portfolio"
                      v-model="selectedPortfolioId"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"
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
                      v-if="!selectedPortfolioId && formErrors.portfolio"
                      class="text-xs text-red-500 mt-1"
                    >
                      {{ formErrors.portfolio }}
                    </p>
                  </div>

                  <!-- Condition -->
                  <ConditionSelect
                    v-model="cardDetails.condition"
                    :error="formErrors.condition"
                  />

                  <!-- Language -->
                  <LanguageSelect
                    v-model="cardDetails.language"
                    :error="formErrors.language"
                  />

                  <!-- Quantity -->
                  <div>
                    <label
                      for="quantity"
                      class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                      Quantity
                    </label>
                    <input
                      id="quantity"
                      v-model.number="cardDetails.quantity"
                      type="number"
                      min="1"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"
                    />
                    <p
                      v-if="!cardDetails.quantity && formErrors.quantity"
                      class="text-xs text-red-500 mt-1"
                    >
                      {{ formErrors.quantity }}
                    </p>
                  </div>

                  <!-- Purchase Price -->
                  <PriceInput
                    v-model="cardDetails.purchase_price"
                    label="Purchase Price"
                    :estimated-price="estimatedPrice"
                    :error="formErrors.purchase_price"
                  />

                  <!-- Acquired Date -->
                  <div>
                    <label
                      for="acquiredAt"
                      class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                      Acquired Date (Optional)
                    </label>
                    <input
                      id="acquiredAt"
                      v-model="cardDetails.acquired_at"
                      type="date"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"
                    />
                  </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                  <button
                    type="button"
                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
                    @click="closeModal"
                  >
                    Cancel
                  </button>
                  <button
                    type="submit"
                    :disabled="isSubmitting"
                    class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 disabled:opacity-50"
                  >
                    {{ isSubmitting ? 'Adding...' : 'Add Card' }}
                  </button>
                </div>

                <p
                  v-if="formErrors.general"
                  class="text-xs text-red-500 mt-2 text-right"
                >
                  {{ formErrors.general }}
                </p>
              </form>
            </DialogPanel>
          </TransitionChild>
        </div>
      </div>
    </Dialog>
  </TransitionRoot>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue';
import {
  Dialog,
  DialogPanel,
  DialogTitle,
  TransitionRoot,
  TransitionChild,
} from '@headlessui/vue';
import { usePortfolioStore, type Blueprint } from '@/stores/portfolioStore';
import { useCardPricing } from '@/composables/useCardPricing';
import { useApiError } from '@/composables/useApiError';
import ConditionSelect from '@/Components/Forms/ConditionSelect.vue';
import LanguageSelect from '@/Components/Forms/LanguageSelect.vue';
import PriceInput from '@/Components/Forms/PriceInput.vue';

const props = defineProps<{
  blueprint: Blueprint;
}>();

const emit = defineEmits(['close', 'card-added']);

const portfolioStore = usePortfolioStore();
const { isLoading: isSubmitting } = useApiError();

const isOpen = ref(true);
const selectedPortfolioId = ref<number | null>(null);

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
  computed(() => props.blueprint),
  computed(() => cardDetails.condition)
);

const closeModal = () => {
  isOpen.value = false;
  setTimeout(() => emit('close'), 300);
};

const validateForm = (): boolean => {
  Object.keys(formErrors).forEach((key) => delete formErrors[key]);
  let isValid = true;

  if (!selectedPortfolioId.value) {
    formErrors.portfolio = 'Portfolio selection is required.';
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
    // Create UserCard records
    const response = await portfolioStore.createUserCard({
      blueprint_id: props.blueprint.id,
      condition: cardDetails.condition,
      language: cardDetails.language,
      quantity: cardDetails.quantity,
      purchase_price: cardDetails.purchase_price,
      acquired_at: cardDetails.acquired_at,
    });

    const createdCards = Array.isArray(response) ? response : [response];

    if (createdCards.length > 0 && selectedPortfolioId.value) {
      // Add all cards to portfolio
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

onMounted(async () => {
  // Load portfolios if not already loaded
  if (portfolioStore.portfolios.length === 0) {
    await portfolioStore.fetchPortfolios();
  }

  // Auto-select first portfolio if only one exists
  if (portfolioStore.portfolios.length === 1) {
    selectedPortfolioId.value = portfolioStore.portfolios[0].id;
  }

  // Set default language based on blueprint latest price
  if (props.blueprint.latest_price?.language) {
    cardDetails.language = props.blueprint.latest_price.language.toUpperCase();
  }
});
</script>
