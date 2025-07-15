<template>
  <FormModal
    :is-open="isOpen"
    title="Edit Card"
    subtitle="Update your card details"
    size="md"
    :is-loading="isSubmitting"
    :can-submit="isFormValid"
    submit-text="Update Card"
    loading-text="Updating..."
    @close="closeModal"
    @submit="submitEdit"
  >
    <!-- Card Preview -->
    <div
      v-if="userCard.blueprint"
      class="mb-4 p-3 border rounded-md dark:border-gray-600 flex items-center space-x-4"
    >
      <img
        v-if="userCard.blueprint.image_url"
        :src="userCard.blueprint.image_url"
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
          {{ userCard.blueprint.name }}
        </p>
        <p class="text-xs dark:text-gray-300">
          {{ userCard.blueprint.expansion?.name }} ({{
            userCard.blueprint.expansion?.code
          }})
        </p>
      </div>
    </div>

    <!-- Quantity in Portfolio -->
    <div>
      <label
        for="quantity"
        class="block text-sm font-medium text-gray-700 dark:text-gray-300"
      >
        Quantity in Portfolio *
      </label>
      <input
        id="quantity"
        v-model.number="formData.quantity"
        type="number"
        min="1"
        max="999"
        required
        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:focus:border-indigo-400 dark:focus:ring-indigo-400"
      />
    </div>

    <!-- Purchase Price -->
    <PriceInput
      id="purchasePrice"
      v-model="formData.purchase_price"
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
        v-model="formData.acquired_at"
        type="date"
        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:focus:border-indigo-400 dark:focus:ring-indigo-400"
      />
    </div>

    <!-- Notes -->
    <div>
      <label
        for="notes"
        class="block text-sm font-medium text-gray-700 dark:text-gray-300"
      >
        Notes
      </label>
      <textarea
        id="notes"
        v-model="formData.notes"
        rows="3"
        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:focus:border-indigo-400 dark:focus:ring-indigo-400"
        placeholder="Any additional notes about this card..."
        maxlength="1000"
      />
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
        {{ formData.notes?.length || 0 }}/1000 characters
      </p>
    </div>

    <!-- Error Display -->
    <div v-if="formError" class="mt-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-md">
      <p class="text-sm text-red-600 dark:text-red-400">
        {{ formError }}
      </p>
    </div>
  </FormModal>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from 'vue';
import {
  usePortfolioStore,
  UserCard as UserCardInterface,
} from '@/stores/portfolioStore';
import { useCardPricing } from '@/composables/useCardPricing';
import { useApiError } from '@/composables/useApiError';
import { useToast } from '@/composables/useToast';
import FormModal from '@/Components/Base/FormModal.vue';
import PriceInput from '@/Components/Forms/PriceInput.vue';

interface Props {
  userCard: UserCardInterface;
  portfolioId: number;
}

const props = defineProps<Props>();
const emit = defineEmits(['close', 'card-updated']);

const portfolioStore = usePortfolioStore();
const { addToast } = useToast();
const {
  error: formError,
  isLoading: isSubmitting,
  withLoading,
} = useApiError();

const isOpen = ref(true);

const formData = reactive({
  quantity: props.userCard.pivot?.quantity || 1,
  purchase_price: props.userCard.purchase_price_cents
    ? props.userCard.purchase_price_cents / 100
    : null,
  acquired_at: props.userCard.acquired_at
    ? props.userCard.acquired_at.split('T')[0]
    : '',
  notes: props.userCard.notes || '',
});

// Use pricing composable
const blueprintRef = computed(() => props.userCard.blueprint || null);
const conditionRef = computed(() => props.userCard.condition);
const { estimatedPrice } = useCardPricing(blueprintRef, conditionRef);

// Form validation
const isFormValid = computed(() => {
  return formData.quantity >= 1 && 
         formData.quantity <= 999 &&
         (!formData.notes || formData.notes.length <= 1000);
});

const closeModal = () => {
  isOpen.value = false;
  setTimeout(() => emit('close'), 300);
};

const submitEdit = async () => {
  if (!isFormValid.value) return;

  await withLoading(async () => {
    try {
      // Update the UserCard properties
      await portfolioStore.updateUserCard(props.userCard.id, {
        purchase_price: formData.purchase_price,
        acquired_at: formData.acquired_at || null,
        notes: formData.notes || null,
      });

      // Update quantity in portfolio if changed
      if (formData.quantity !== (props.userCard.pivot?.quantity || 1)) {
        await portfolioStore.updateUserCardQuantityInPortfolio(
          props.portfolioId,
          props.userCard.id,
          formData.quantity
        );
      }

      addToast({
        type: 'success',
        title: 'Card updated successfully!',
        description: `"${props.userCard.blueprint?.name}" has been updated.`
      });

      emit('card-updated');
      closeModal();
    } catch (error: any) {
      console.error('Error updating card:', error);
      
      addToast({
        type: 'error',
        title: 'Failed to update card',
        description: error.response?.data?.message || 'An unexpected error occurred. Please try again.'
      });
    }
  });
};
</script>
