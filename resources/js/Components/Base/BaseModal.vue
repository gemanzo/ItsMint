<template>
  <TransitionRoot appear :show="isOpen" as="template">
    <Dialog as="div" class="relative z-10" @close="handleClose">
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
          class="flex min-h-full items-end justify-center p-2 sm:p-4 text-center sm:items-center"
        >
          <TransitionChild
            as="template"
            enter="duration-300 ease-out"
            enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            enter-to="opacity-100 translate-y-0 sm:scale-100"
            leave="duration-200 ease-in"
            leave-from="opacity-100 translate-y-0 sm:scale-100"
            leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
          >
            <DialogPanel
              :class="[
                'w-full transform overflow-hidden rounded-t-2xl sm:rounded-2xl bg-white dark:bg-gray-800 text-left align-middle shadow-xl transition-all',
                'max-h-[85vh] sm:max-h-[90vh] flex flex-col',
                sizeClasses[size],
              ]"
            >
              <!-- Header -->
              <div v-if="$slots.header || title" class="flex-shrink-0 p-4 sm:p-6 border-b border-gray-200 dark:border-gray-700">
                <slot name="header">
                  <div class="flex items-start">
                    <!-- Icon slot -->
                    <div v-if="$slots.icon" class="flex-shrink-0 mr-3 sm:mr-4">
                      <slot name="icon" />
                    </div>

                    <div class="flex-1 min-w-0">
                      <DialogTitle
                        as="h3"
                        class="text-lg sm:text-xl font-semibold leading-6 text-gray-900 dark:text-gray-100 pr-8"
                      >
                        {{ title }}
                      </DialogTitle>
                      <p
                        v-if="subtitle"
                        class="mt-1 text-sm text-gray-600 dark:text-gray-400 pr-8"
                      >
                        {{ subtitle }}
                      </p>
                    </div>

                    <!-- Close button -->
                    <button
                      v-if="showCloseButton"
                      class="absolute top-4 right-4 p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500"
                      @click="handleClose"
                      aria-label="Close modal"
                    >
                      <svg
                        class="w-5 h-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M6 18L18 6M6 6l12 12"
                        />
                      </svg>
                    </button>
                  </div>
                </slot>
              </div>

              <!-- Content -->
              <div class="flex-1 overflow-y-auto p-4 sm:p-6">
                <slot />
              </div>

              <!-- Footer -->
              <div v-if="$slots.footer" class="flex-shrink-0 border-t border-gray-200 dark:border-gray-700 p-4 sm:p-6">
                <slot name="footer" />
              </div>
            </DialogPanel>
          </TransitionChild>
        </div>
      </div>
    </Dialog>
  </TransitionRoot>
</template>

<script setup lang="ts">
import {
  Dialog,
  DialogPanel,
  DialogTitle,
  TransitionChild,
  TransitionRoot,
} from '@headlessui/vue';

interface Props {
  isOpen: boolean;
  title?: string;
  subtitle?: string;
  size?: 'sm' | 'md' | 'lg' | 'xl' | '2xl';
  closeable?: boolean;
  showCloseButton?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  size: 'md',
  closeable: true,
  showCloseButton: false,
});

const emit = defineEmits<{
  close: [];
}>();

const sizeClasses = {
  sm: 'sm:max-w-sm',
  md: 'sm:max-w-md',
  lg: 'sm:max-w-lg',
  xl: 'sm:max-w-xl',
  '2xl': 'sm:max-w-2xl',
};

const handleClose = () => {
  if (props.closeable) {
    emit('close');
  }
};
</script>
