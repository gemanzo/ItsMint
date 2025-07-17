<template>
  <BaseModal
    :is-open="isOpen"
    :title="title"
    :size="size"
    :closeable="!isLoading"
    @close="handleClose"
  >
    <template #icon>
      <div
        :class="[
          'flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full sm:mx-0 sm:h-10 sm:w-10',
          iconClasses[type],
        ]"
      >
        <component
          :is="iconComponent"
          :class="['h-6 w-6', iconColorClasses[type]]"
        />
      </div>
    </template>

    <div class="mt-2">
      <p class="text-sm text-gray-600 dark:text-gray-400">
        {{ message }}
      </p>

      <!-- Slot for additional content (like form fields for secure deletion) -->
      <div v-if="$slots.default" class="mt-4">
        <slot />
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end space-x-3">
        <SecondaryButton :disabled="isLoading" @click="handleClose">
          {{ cancelText }}
        </SecondaryButton>

        <component
          :is="confirmButtonComponent"
          :disabled="isLoading || !canConfirm"
          :class="{ 'opacity-50 cursor-not-allowed': isLoading || !canConfirm }"
          @click="handleConfirm"
        >
          <svg
            v-if="isLoading"
            class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
            xmlns="http://www.w3.org/2000/svg"
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
          {{ isLoading ? loadingText : confirmText }}
        </component>
      </div>
    </template>
  </BaseModal>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import BaseModal from './BaseModal.vue';
import PrimaryButton from '../PrimaryButton.vue';
import SecondaryButton from '../SecondaryButton.vue';
import DangerButton from '../DangerButton.vue';

// Icons
import ExclamationTriangleIcon from '@heroicons/vue/24/outline/ExclamationTriangleIcon';
import InformationCircleIcon from '@heroicons/vue/24/outline/InformationCircleIcon';
import CheckCircleIcon from '@heroicons/vue/24/outline/CheckCircleIcon';

type ConfirmationType = 'danger' | 'warning' | 'info' | 'success';

interface Props {
  isOpen: boolean;
  title: string;
  message: string;
  type?: ConfirmationType;
  size?: 'sm' | 'md' | 'lg' | 'xl' | '2xl';
  isLoading?: boolean;
  canConfirm?: boolean;
  confirmText?: string;
  cancelText?: string;
  loadingText?: string;
}

const props = withDefaults(defineProps<Props>(), {
  type: 'danger',
  size: 'sm',
  isLoading: false,
  canConfirm: true,
  confirmText: 'Confirm',
  cancelText: 'Cancel',
  loadingText: 'Processing...',
});

const emit = defineEmits<{
  close: [];
  confirm: [];
}>();

const iconClasses = {
  danger: 'bg-red-100 dark:bg-red-900/30',
  warning: 'bg-yellow-100 dark:bg-yellow-900/30',
  info: 'bg-blue-100 dark:bg-blue-900/30',
  success: 'bg-green-100 dark:bg-green-900/30',
};

const iconColorClasses = {
  danger: 'text-red-600 dark:text-red-400',
  warning: 'text-yellow-600 dark:text-yellow-400',
  info: 'text-blue-600 dark:text-blue-400',
  success: 'text-green-600 dark:text-green-400',
};

const iconComponent = computed(() => {
  switch (props.type) {
    case 'danger':
    case 'warning':
      return ExclamationTriangleIcon;
    case 'info':
      return InformationCircleIcon;
    case 'success':
      return CheckCircleIcon;
    default:
      return ExclamationTriangleIcon;
  }
});

const confirmButtonComponent = computed(() => {
  return props.type === 'danger' ? DangerButton : PrimaryButton;
});

const handleClose = () => {
  if (!props.isLoading) {
    emit('close');
  }
};

const handleConfirm = () => {
  if (!props.isLoading && props.canConfirm) {
    emit('confirm');
  }
};
</script>
