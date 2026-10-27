<template>
  <div class="relative inline-block">
    <!-- Trigger element -->
    <div
      class="cursor-help"
      @mouseenter="showTooltip = true"
      @mouseleave="showTooltip = false"
    >
      <slot />
    </div>

    <!-- Tooltip -->
    <Transition
      enter-active-class="transition ease-out duration-200"
      enter-from-class="opacity-0 transform scale-95"
      enter-to-class="opacity-100 transform scale-100"
      leave-active-class="transition ease-in duration-150"
      leave-from-class="opacity-100 transform scale-100"
      leave-to-class="opacity-0 transform scale-95"
    >
      <div
        v-show="showTooltip"
        :class="[
          'absolute z-50 px-3 py-2 text-sm text-white bg-gray-900 dark:bg-gray-700 rounded-lg shadow-lg',
          'min-w-[200px] max-w-[300px]',
          position === 'top'
            ? 'bottom-full left-1/2 transform -translate-x-1/2 mb-2'
            : '',
          position === 'bottom'
            ? 'top-full left-1/2 transform -translate-x-1/2 mt-2'
            : '',
          position === 'left'
            ? 'right-full top-1/2 transform -translate-y-1/2 mr-2'
            : '',
          position === 'right'
            ? 'left-full top-1/2 transform -translate-y-1/2 ml-2'
            : '',
        ]"
      >
        <div class="text-xs leading-relaxed">
          {{ content }}
        </div>

        <!-- Arrow -->
        <div
          :class="[
            'absolute w-2 h-2 bg-gray-900 dark:bg-gray-700 transform rotate-45',
            position === 'top'
              ? 'top-full left-1/2 transform -translate-x-1/2 -mt-1'
              : '',
            position === 'bottom'
              ? 'bottom-full left-1/2 transform -translate-x-1/2 -mb-1'
              : '',
            position === 'left'
              ? 'left-full top-1/2 transform -translate-y-1/2 -ml-1'
              : '',
            position === 'right'
              ? 'right-full top-1/2 transform -translate-y-1/2 -mr-1'
              : '',
          ]"
        />
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';

interface Props {
  content: string;
  position?: 'top' | 'bottom' | 'left' | 'right';
}

withDefaults(defineProps<Props>(), {
  position: 'top',
});

const showTooltip = ref(false);
</script>
