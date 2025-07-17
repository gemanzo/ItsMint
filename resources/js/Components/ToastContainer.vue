<template>
  <teleport to="body">
    <div
      class="fixed top-4 right-4 z-50 space-y-2"
      role="region"
      aria-label="Notifications"
      aria-live="polite"
    >
      <TransitionGroup name="toast" tag="div" class="space-y-2">
        <div
          v-for="toast in toasts"
          :key="toast.id"
          class="flex items-start p-4 max-w-sm rounded-lg shadow-lg border"
          :class="getToastClasses(toast.type)"
          role="alert"
        >
          <!-- Icon -->
          <div class="flex-shrink-0">
            <CheckCircleIcon
              v-if="toast.type === 'success'"
              class="h-5 w-5"
              :class="getIconClasses(toast.type)"
            />
            <ExclamationTriangleIcon
              v-else-if="toast.type === 'warning'"
              class="h-5 w-5"
              :class="getIconClasses(toast.type)"
            />
            <XCircleIcon
              v-else-if="toast.type === 'error'"
              class="h-5 w-5"
              :class="getIconClasses(toast.type)"
            />
            <InformationCircleIcon
              v-else
              class="h-5 w-5"
              :class="getIconClasses(toast.type)"
            />
          </div>

          <!-- Content -->
          <div class="ml-3 flex-1">
            <h4
              class="text-sm font-medium"
              :class="getTitleClasses(toast.type)"
            >
              {{ toast.title }}
            </h4>
            <p
              v-if="toast.message"
              class="mt-1 text-sm"
              :class="getMessageClasses(toast.type)"
            >
              {{ toast.message }}
            </p>
          </div>

          <!-- Close button -->
          <div class="ml-4 flex-shrink-0 flex">
            <button
              type="button"
              class="inline-flex rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2"
              :class="getCloseButtonClasses(toast.type)"
              @click="removeToast(toast.id)"
            >
              <span class="sr-only">Close</span>
              <XMarkIcon class="h-4 w-4" />
            </button>
          </div>
        </div>
      </TransitionGroup>
    </div>
  </teleport>
</template>

<script setup lang="ts">
import {
  CheckCircleIcon,
  ExclamationTriangleIcon,
  XCircleIcon,
  InformationCircleIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline';
import { useToast, type Toast } from '@/composables/useToast';

const { toasts, removeToast } = useToast();

const getToastClasses = (type: Toast['type']): string => {
  const baseClasses = 'bg-white dark:bg-gray-800 border';

  switch (type) {
    case 'success':
      return `${baseClasses} border-green-200 dark:border-green-700`;
    case 'error':
      return `${baseClasses} border-red-200 dark:border-red-700`;
    case 'warning':
      return `${baseClasses} border-yellow-200 dark:border-yellow-700`;
    case 'info':
      return `${baseClasses} border-blue-200 dark:border-blue-700`;
    default:
      return `${baseClasses} border-gray-200 dark:border-gray-700`;
  }
};

const getIconClasses = (type: Toast['type']): string => {
  switch (type) {
    case 'success':
      return 'text-green-600 dark:text-green-400';
    case 'error':
      return 'text-red-600 dark:text-red-400';
    case 'warning':
      return 'text-yellow-600 dark:text-yellow-400';
    case 'info':
      return 'text-blue-600 dark:text-blue-400';
    default:
      return 'text-gray-600 dark:text-gray-400';
  }
};

const getTitleClasses = (type: Toast['type']): string => {
  switch (type) {
    case 'success':
      return 'text-green-800 dark:text-green-200';
    case 'error':
      return 'text-red-800 dark:text-red-200';
    case 'warning':
      return 'text-yellow-800 dark:text-yellow-200';
    case 'info':
      return 'text-blue-800 dark:text-blue-200';
    default:
      return 'text-gray-800 dark:text-gray-200';
  }
};

const getMessageClasses = (type: Toast['type']): string => {
  switch (type) {
    case 'success':
      return 'text-green-700 dark:text-green-300';
    case 'error':
      return 'text-red-700 dark:text-red-300';
    case 'warning':
      return 'text-yellow-700 dark:text-yellow-300';
    case 'info':
      return 'text-blue-700 dark:text-blue-300';
    default:
      return 'text-gray-700 dark:text-gray-300';
  }
};

const getCloseButtonClasses = (type: Toast['type']): string => {
  const baseClasses = 'focus:ring-offset-2';

  switch (type) {
    case 'success':
      return `${baseClasses} text-green-400 hover:text-green-600 focus:ring-green-500`;
    case 'error':
      return `${baseClasses} text-red-400 hover:text-red-600 focus:ring-red-500`;
    case 'warning':
      return `${baseClasses} text-yellow-400 hover:text-yellow-600 focus:ring-yellow-500`;
    case 'info':
      return `${baseClasses} text-blue-400 hover:text-blue-600 focus:ring-blue-500`;
    default:
      return `${baseClasses} text-gray-400 hover:text-gray-600 focus:ring-gray-500`;
  }
};
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.3s ease;
}

.toast-enter-from {
  opacity: 0;
  transform: translateX(100%);
}

.toast-leave-to {
  opacity: 0;
  transform: translateX(100%);
}

.toast-move {
  transition: transform 0.3s ease;
}
</style>
