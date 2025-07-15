<template>
  <Modal :show="show" @close="close">
    <div class="p-6 bg-white dark:bg-gray-800 rounded-lg">
      <div class="flex items-start">
        <div
          class="flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900/30 sm:mx-0 sm:h-10 sm:w-10"
        >
          <svg
            class="h-6 w-6 text-red-600 dark:text-red-400"
            stroke="currentColor"
            fill="none"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
            />
          </svg>
        </div>
        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
          <h3
            id="modal-title"
            class="text-lg leading-6 font-bold text-gray-900 dark:text-gray-100"
          >
            Delete Portfolio
          </h3>
          <div class="mt-2">
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
              This action <strong>cannot be undone</strong>. This will permanently delete the
              <strong class="text-gray-900 dark:text-gray-100">"{{ portfolioName }}"</strong>
              portfolio and all of its data.
            </p>
            
            <div class="mt-4">
              <label 
                for="portfolio-name-confirmation" 
                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
              >
                Type <strong>{{ portfolioName }}</strong> to confirm:
              </label>
              <TextInput
                id="portfolio-name-confirmation"
                ref="nameInput"
                v-model="confirmationText"
                type="text"
                class="block w-full"
                :class="{
                  'border-red-500 focus:border-red-500 focus:ring-red-500': confirmationText && !isNameValid,
                  'border-green-500 focus:border-green-500 focus:ring-green-500': isNameValid
                }"
                placeholder="Enter portfolio name to confirm deletion"
                @keyup.enter="isNameValid && confirm()"
              />
              <p 
                v-if="confirmationText && !isNameValid" 
                class="mt-1 text-xs text-red-600 dark:text-red-400"
              >
                Portfolio name doesn't match
              </p>
            </div>
          </div>
        </div>
      </div>

      <div class="mt-6 flex justify-end space-x-3">
        <SecondaryButton @click="close">
          Cancel
        </SecondaryButton>
        <DangerButton 
          :disabled="!isNameValid || isDeleting"
          @click="confirm"
        >
          <span v-if="isDeleting" class="flex items-center">
            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Deleting...
          </span>
          <span v-else>Delete Portfolio</span>
        </DangerButton>
      </div>
    </div>
  </Modal>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue';
import Modal from '@/Components/Modal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

interface Props {
  show: boolean;
  portfolioName: string;
  isDeleting?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  isDeleting: false,
});

const emit = defineEmits(['close', 'confirm']);

const confirmationText = ref('');
const nameInput = ref<InstanceType<typeof TextInput> | null>(null);

const isNameValid = computed(() => {
  return confirmationText.value === props.portfolioName;
});

const close = () => {
  confirmationText.value = '';
  emit('close');
};

const confirm = () => {
  if (!isNameValid.value || props.isDeleting) return;
  emit('confirm');
};

// Focus the input when modal opens
watch(() => props.show, (isShown) => {
  if (isShown) {
    confirmationText.value = '';
    nextTick(() => {
      nameInput.value?.$el?.focus();
    });
  }
});
</script> 