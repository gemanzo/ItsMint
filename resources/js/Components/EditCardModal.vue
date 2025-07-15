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
              class="w-full max-w-md transform overflow-hidden rounded-2xl bg-white dark:bg-gray-800 p-6 text-left align-middle shadow-xl transition-all"
            >
              <DialogTitle
                as="h3"
                class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100"
              >
                Edit Card
              </DialogTitle>

              <!-- Card Info -->
              <div
                v-if="userCard.blueprint"
                class="mt-4 p-3 border rounded-md dark:border-gray-600 flex items-center space-x-4"
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
                  >No Img</span
                >
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

              <form @submit.prevent="submitEdit">
                <div class="mt-4 space-y-4">
                  <!-- Quantity in Portfolio -->
                  <div>
                    <label
                      for="quantity"
                      class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                      >Quantity in Portfolio</label
                    >
                    <input
                      id="quantity"
                      v-model.number="formData.quantity"
                      type="number"
                      min="1"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"
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
                      >Acquired Date (Optional)</label
                    >
                    <input
                      id="acquiredAt"
                      v-model="formData.acquired_at"
                      type="date"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"
                    />
                  </div>

                  <!-- Notes -->
                  <div>
                    <label
                      for="notes"
                      class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                      >Notes (Optional)</label
                    >
                    <textarea
                      id="notes"
                      v-model="formData.notes"
                      rows="3"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"
                      placeholder="Any additional notes about this card..."
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
                    {{ isSubmitting ? 'Updating...' : 'Update Card' }}
                  </button>
                </div>
                <p
                  v-if="formError"
                  class="text-xs text-red-500 mt-2 text-right"
                >
                  {{ formError }}
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
import { ref, reactive, computed } from 'vue';
import {
  Dialog,
  DialogPanel,
  DialogTitle,
  TransitionRoot,
  TransitionChild,
} from '@headlessui/vue';
import {
  usePortfolioStore,
  UserCard as UserCardInterface,
} from '@/stores/portfolioStore';
import { useCardPricing } from '@/composables/useCardPricing';
import { useApiError } from '@/composables/useApiError';
import PriceInput from '@/Components/Forms/PriceInput.vue';

interface Props {
  userCard: UserCardInterface;
  portfolioId: number;
}

const props = defineProps<Props>();
const emit = defineEmits(['close', 'card-updated']);

const portfolioStore = usePortfolioStore();
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

const closeModal = () => {
  isOpen.value = false;
  setTimeout(() => emit('close'), 300);
};

const submitEdit = async () => {
  await withLoading(async () => {
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

    emit('card-updated');
    closeModal();
  });
};
</script>
