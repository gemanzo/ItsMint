<template>
  <BaseModal :is-open="show" @close="$emit('close')">
    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
      <div class="sm:flex sm:items-start">
        <div
          class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900/30 sm:mx-0 sm:h-10 sm:w-10"
        >
          <svg
            class="h-6 w-6 text-red-600 dark:text-red-400"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
            />
          </svg>
        </div>
        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
          <h3
            class="text-lg leading-6 font-medium text-gray-900 dark:text-white"
          >
            Remove Cards from Portfolio
          </h3>
          <div class="mt-2">
            <p class="text-sm text-gray-500 dark:text-gray-400">
              How many copies of
              <strong>{{ cardGroup?.blueprint?.name }}</strong> would you like
              to remove?
            </p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
              Available: {{ cardGroup?.totalQuantity }} cards
            </p>
          </div>

          <!-- Card Preview -->
          <div class="mt-4 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
            <div class="flex items-center space-x-3">
              <div v-if="cardGroup?.blueprint?.image_url" class="flex-shrink-0">
                <img
                  :src="cardGroup.blueprint.image_url"
                  :alt="cardGroup.blueprint.name"
                  class="h-16 w-auto object-contain rounded"
                />
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 dark:text-white">
                  {{ cardGroup?.blueprint?.name }}
                </p>
                <div class="flex flex-wrap gap-2 mt-1">
                  <span
                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200"
                  >
                    {{ cardGroup?.condition }}
                  </span>
                  <span
                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200"
                  >
                    {{ cardGroup?.language }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Quantity Selection -->
          <div class="mt-4">
            <label
              class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
            >
              Quantity to remove
            </label>
            <div class="flex items-center space-x-3">
              <button
                type="button"
                :disabled="quantityToRemove <= 1"
                class="p-1 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed"
                @click="quantityToRemove = Math.max(1, quantityToRemove - 1)"
              >
                <svg
                  class="h-4 w-4"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M20 12H4"
                  />
                </svg>
              </button>
              <input
                v-model.number="quantityToRemove"
                type="number"
                :min="1"
                :max="cardGroup?.totalQuantity || 1"
                class="w-20 px-3 py-2 text-center border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-red-500 focus:border-red-500"
              />
              <button
                type="button"
                :disabled="quantityToRemove >= (cardGroup?.totalQuantity || 1)"
                class="p-1 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed"
                @click="
                  quantityToRemove = Math.min(
                    cardGroup?.totalQuantity || 1,
                    quantityToRemove + 1
                  )
                "
              >
                <svg
                  class="h-4 w-4"
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
              </button>
            </div>

            <!-- Quick Action Buttons -->
            <div class="flex gap-2 mt-3">
              <button
                type="button"
                class="px-3 py-1 text-xs bg-gray-100 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-200 dark:hover:bg-gray-500"
                @click="quantityToRemove = 1"
              >
                Remove 1
              </button>
              <button
                v-if="(cardGroup?.totalQuantity || 0) >= 5"
                type="button"
                class="px-3 py-1 text-xs bg-gray-100 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-200 dark:hover:bg-gray-500"
                @click="
                  quantityToRemove = Math.min(5, cardGroup?.totalQuantity || 1)
                "
              >
                Remove 5
              </button>
              <button
                type="button"
                class="px-3 py-1 text-xs bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 rounded-md hover:bg-red-200 dark:hover:bg-red-900/50"
                @click="quantityToRemove = cardGroup?.totalQuantity || 1"
              >
                Remove All ({{ cardGroup?.totalQuantity }})
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div
      class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse"
    >
      <button
        type="button"
        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
        :disabled="isProcessing"
        @click="confirmRemove"
      >
        <svg
          v-if="isProcessing"
          class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
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
        Remove {{ quantityToRemove }}
        {{ quantityToRemove === 1 ? 'Card' : 'Cards' }}
      </button>
      <button
        type="button"
        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
        :disabled="isProcessing"
        @click="$emit('close')"
      >
        Cancel
      </button>
    </div>
  </BaseModal>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import BaseModal from '@/Components/Base/BaseModal.vue';

interface CardGroup {
  groupKey: string;
  blueprint?: {
    id: number;
    name: string;
    image_url?: string;
  };
  condition: string;
  language: string;
  totalQuantity: number;
}

interface Props {
  show: boolean;
  cardGroup: CardGroup | null;
}

interface Emits {
  (e: 'close'): void;
  (e: 'confirm', quantity: number): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

const quantityToRemove = ref(1);
const isProcessing = ref(false);

// Reset quantity when modal opens with new card group
watch(
  () => [props.show, props.cardGroup],
  ([show, cardGroup]) => {
    if (show && cardGroup) {
      quantityToRemove.value = 1;
      isProcessing.value = false;
    }
  }
);

const confirmRemove = async () => {
  if (!props.cardGroup) return;

  isProcessing.value = true;
  try {
    emit('confirm', quantityToRemove.value);
  } finally {
    isProcessing.value = false;
  }
};
</script>
