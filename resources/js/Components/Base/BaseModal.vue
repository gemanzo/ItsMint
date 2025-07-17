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
              :class="[
                'w-full transform overflow-hidden rounded-2xl bg-white dark:bg-gray-800 p-6 text-left align-middle shadow-xl transition-all',
                sizeClasses[size],
              ]"
            >
              <!-- Header -->
              <div v-if="$slots.header || title" class="mb-4">
                <slot name="header">
                  <div class="flex items-center">
                    <!-- Icon slot -->
                    <div v-if="$slots.icon" class="flex-shrink-0 mr-4">
                      <slot name="icon" />
                    </div>

                    <div class="flex-1">
                      <DialogTitle
                        as="h3"
                        class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100"
                      >
                        {{ title }}
                      </DialogTitle>
                      <p
                        v-if="subtitle"
                        class="mt-1 text-sm text-gray-600 dark:text-gray-400"
                      >
                        {{ subtitle }}
                      </p>
                    </div>

                    <!-- Close button -->
                    <button
                      v-if="showCloseButton"
                      class="ml-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors"
                      @click="handleClose"
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
              <div class="flex-1">
                <slot />
              </div>

              <!-- Footer -->
              <div v-if="$slots.footer" class="mt-6">
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
  sm: 'max-w-sm',
  md: 'max-w-md',
  lg: 'max-w-lg',
  xl: 'max-w-xl',
  '2xl': 'max-w-2xl',
};

const handleClose = () => {
  if (props.closeable) {
    emit('close');
  }
};
</script>
