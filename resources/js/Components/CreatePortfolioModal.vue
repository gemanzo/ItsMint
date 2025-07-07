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
                Create New Portfolio
              </DialogTitle>
              <form @submit.prevent="submitCreatePortfolio">
                <div class="mt-4 space-y-4">
                  <div>
                    <label
                      for="portfolioName"
                      class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                      >Portfolio Name</label
                    >
                    <input
                      id="portfolioName"
                      v-model="portfolioDetails.name"
                      type="text"
                      required
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"
                    />
                    <p v-if="formErrors.name" class="text-xs text-red-500 mt-1">
                      {{ formErrors.name }}
                    </p>
                  </div>

                  <div>
                    <label
                      for="portfolioDescription"
                      class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                      >Description (Optional)</label
                    >
                    <textarea
                      id="portfolioDescription"
                      v-model="portfolioDetails.description"
                      rows="3"
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
                    {{ isSubmitting ? 'Creating...' : 'Create Portfolio' }}
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
import { ref, reactive } from 'vue';
import {
  Dialog,
  DialogPanel,
  DialogTitle,
  TransitionRoot,
  TransitionChild,
} from '@headlessui/vue';
import { usePortfolioStore } from '@/stores/portfolioStore'; // Adjusted path assuming common alias

const emit = defineEmits(['close', 'portfolio-created']);
const portfolioStore = usePortfolioStore();

const isOpen = ref(true); // Modal is open by default
const isSubmitting = ref(false);

const portfolioDetails = reactive({
  name: '',
  description: '',
});

const formErrors = reactive<Record<string, string>>({});

const closeModal = () => {
  isOpen.value = false;
  setTimeout(() => emit('close'), 300); // Allow time for transition
};

const validateForm = (): boolean => {
  Object.keys(formErrors).forEach((key) => delete formErrors[key]);
  let isValid = true;
  if (!portfolioDetails.name.trim()) {
    formErrors.name = 'Portfolio name is required.';
    isValid = false;
  }
  return isValid;
};

const submitCreatePortfolio = async () => {
  if (!validateForm()) return;

  isSubmitting.value = true;
  formErrors.general = '';

  try {
    await portfolioStore.createPortfolio(
      portfolioDetails.name,
      portfolioDetails.description
    );
    emit('portfolio-created');
    closeModal();
  } catch (error) {
    console.error('Error creating portfolio:', error);
    // Use type assertion to avoid 'any' and TS error
    const err = error as {
      response?: { data?: { message?: string } };
      message?: string;
    };
    formErrors.general =
      err.response?.data?.message ??
      err.message ??
      'An unknown error occurred while creating the portfolio.';
  } finally {
    isSubmitting.value = false;
  }
};
</script>
