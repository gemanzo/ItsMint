<template>
  <FormModal
    :is-open="isOpen"
    title="Add Card to Portfolio"
    subtitle="Add this card to one of your portfolios"
    size="lg"
    :is-loading="isSubmitting"
    :can-submit="isFormValid"
    submit-text="Add Card"
    loading-text="Adding..."
    @close="closeModal"
    @submit="submitAddCard"
  >
    <!-- Card Preview -->
    <div
      class="mb-4 p-3 border rounded-md dark:border-gray-600 flex items-center space-x-4"
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

    <!-- Portfolio Selection -->
    <div>
      <label
        for="portfolio"
        class="block text-sm font-medium text-gray-700 dark:text-gray-300"
      >
        Select Portfolio *
      </label>
      <select
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
      :error="formErrors.purchase_price"
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
  </FormModal>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue';
import { usePortfolioStore, type Blueprint } from '@/stores/portfolioStore';
import { useCardPricing } from '@/composables/useCardPricing';
import { useApiError } from '@/composables/useApiError';
import { useToast } from '@/composables/useToast';
import FormModal from '@/Components/Base/FormModal.vue';
import ConditionSelect from '@/Components/Forms/ConditionSelect.vue';
import LanguageSelect from '@/Components/Forms/LanguageSelect.vue';
import PriceInput from '@/Components/Forms/PriceInput.vue';

const props = defineProps<{
  blueprint: Blueprint;
}>();

const emit = defineEmits(['close', 'card-added']);

const portfolioStore = usePortfolioStore();
const { isLoading: isSubmitting } = useApiError();
const { success: showSuccessToast, error: showErrorToast } = useToast();

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

// Form validation
const isFormValid = computed(() => {
  return (
    selectedPortfolioId.value !== null &&
    cardDetails.quantity >= 1 &&
    cardDetails.quantity <= 999 &&
    !!cardDetails.condition &&
    !!cardDetails.language
  );
});

const closeModal = () => {
  isOpen.value = false;
  setTimeout(() => emit('close'), 300);
};

const validateForm = (): boolean => {
  Object.keys(formErrors).forEach((key) => delete formErrors[key]);
  let isValid = true;

  if (!selectedPortfolioId.value) {
    formErrors.portfolio = 'Please select a portfolio.';
    isValid = false;
  }

  if (!cardDetails.quantity || cardDetails.quantity < 1) {
    formErrors.quantity = 'Quantity must be at least 1.';
    isValid = false;
  }

  if (!cardDetails.condition) {
    formErrors.condition = 'Please select a condition.';
    isValid = false;
  }

  if (!cardDetails.language) {
    formErrors.language = 'Please select a language.';
    isValid = false;
  }

  return isValid;
};

const submitAddCard = async () => {
  if (!validateForm() || !isFormValid.value) return;

  isSubmitting.value = true;

  try {
    // First create the user card
    const createdCard = await portfolioStore.createUserCard({
      blueprint_id: props.blueprint.id,
      condition: cardDetails.condition,
      language: cardDetails.language,
      purchase_price: cardDetails.purchase_price,
      acquired_at: cardDetails.acquired_at || undefined,
      notes: undefined,
      quantity: cardDetails.quantity,
    });

    // Then add it to the portfolio
    const userCards = Array.isArray(createdCard) ? createdCard : [createdCard];
    if (userCards.length > 0) {
      await portfolioStore.addUserCardToPortfolio(
        selectedPortfolioId.value!,
        userCards[0].id,
        cardDetails.quantity
      );

      // Get portfolio name for success message
      const selectedPortfolio = portfolioStore.portfolios.find(
        (p) => p.id === selectedPortfolioId.value
      );

      // Show success toast
      showSuccessToast(
        'Card added successfully!',
        `${props.blueprint.name} has been added to "${selectedPortfolio?.name || 'Portfolio'}" (${cardDetails.quantity}x)`
      );

      emit('card-added', {
        portfolioId: selectedPortfolioId.value,
        cards: userCards,
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
    const finalErrorMessage =
      axiosError.response?.data?.message || errorMessage;

    // Show error toast
    showErrorToast('Error adding card', finalErrorMessage);

    formErrors.general = finalErrorMessage;
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

  // Only set language if user hasn't explicitly changed it
  // Keep EN as default to avoid confusion
});
</script>
