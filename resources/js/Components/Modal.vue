<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = withDefaults(
  defineProps<{
    show?: boolean;
    maxWidth?: 'sm' | 'md' | 'lg' | 'xl' | '2xl';
    closeable?: boolean;
  }>(),
  {
    show: false,
    maxWidth: '2xl',
    closeable: true,
  }
);

const emit = defineEmits(['close']);
const dialog = ref();
const showSlot = ref(props.show);

const openDialog = () => {
  document.body.style.overflow = 'hidden';
  showSlot.value = true;

  dialog.value?.showModal();
};

const closeDialog = () => {
  document.body.style.overflow = '';

  // Wait for the leave transition before closing the native dialog
  setTimeout(() => {
    dialog.value?.close();
    showSlot.value = false;
  }, 200);
};

watch(
  () => props.show,
  () => {
    if (props.show) {
      openDialog();
    } else {
      closeDialog();
    }
  }
);

const close = () => {
  if (props.closeable) {
    emit('close');
  }
};

const closeOnEscape = (e: KeyboardEvent) => {
  if (e.key === 'Escape') {
    e.preventDefault();

    if (props.show) {
      close();
    }
  }
};

onMounted(() => {
  document.addEventListener('keydown', closeOnEscape);

  // A modal mounted already open (e.g. rendered with v-if and :show="true") never
  // triggers the watcher above: the native <dialog> must be opened here, otherwise
  // it stays closed (display: none) and nothing appears.
  if (props.show) {
    openDialog();
  }
});

onUnmounted(() => {
  document.removeEventListener('keydown', closeOnEscape);

  document.body.style.overflow = '';
});

const maxWidthClass = computed(() => {
  return {
    sm: 'sm:max-w-sm',
    md: 'sm:max-w-md',
    lg: 'sm:max-w-lg',
    xl: 'sm:max-w-xl',
    '2xl': 'sm:max-w-2xl',
  }[props.maxWidth];
});
</script>

<template>
  <dialog
    ref="dialog"
    class="z-50 m-0 min-h-full min-w-full overflow-y-auto bg-transparent backdrop:bg-transparent"
  >
    <div
      class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0"
      scroll-region
    >
      <Transition
        enter-active-class="ease-out duration-300"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="ease-in duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-show="show"
          class="fixed inset-0 transform transition-all"
          @click="close"
        >
          <div
            class="absolute inset-0 bg-gray-500 opacity-75 dark:bg-gray-900"
          />
        </div>
      </Transition>

      <Transition
        enter-active-class="ease-out duration-300"
        enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        enter-to-class="opacity-100 translate-y-0 sm:scale-100"
        leave-active-class="ease-in duration-200"
        leave-from-class="opacity-100 translate-y-0 sm:scale-100"
        leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
      >
        <div
          v-show="show"
          class="mb-6 transform overflow-hidden rounded-lg bg-white shadow-xl transition-all sm:mx-auto sm:w-full dark:bg-gray-800"
          :class="maxWidthClass"
        >
          <slot v-if="showSlot" />
        </div>
      </Transition>
    </div>
  </dialog>
</template>
