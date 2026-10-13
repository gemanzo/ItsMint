<template>
  <span
    v-if="show"
    :class="[
      'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold transition-all',
      variantClasses,
      sizeClasses
    ]"
    :title="tooltip"
  >
    <svg
      v-if="showIcon"
      class="w-3.5 h-3.5"
      fill="currentColor"
      viewBox="0 0 20 20"
      xmlns="http://www.w3.org/2000/svg"
    >
      <path
        fill-rule="evenodd"
        d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z"
        clip-rule="evenodd"
      />
    </svg>
    <span>{{ text }}</span>
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useFeatures } from '@/composables/useFeatures';

interface Props {
  /**
   * Whether to show the badge (defaults to checking if user is NOT premium)
   */
  show?: boolean;
  /**
   * Badge text (default: "Premium")
   */
  text?: string;
  /**
   * Show icon (default: true)
   */
  showIcon?: boolean;
  /**
   * Tooltip text
   */
  tooltip?: string;
  /**
   * Badge variant: 'default' | 'subtle' | 'outline'
   */
  variant?: 'default' | 'subtle' | 'outline';
  /**
   * Badge size: 'sm' | 'md' | 'lg'
   */
  size?: 'sm' | 'md' | 'lg';
  /**
   * Force show even if user is premium (useful for showcasing)
   */
  forceShow?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  show: undefined,
  text: 'Premium',
  showIcon: true,
  tooltip: 'Premium feature - upgrade to unlock',
  variant: 'default',
  size: 'md',
  forceShow: false,
});

const { isPremium } = useFeatures();

const show = computed(() => {
  if (props.forceShow) return true;
  if (props.show !== undefined) return props.show;
  return !isPremium.value;
});

const variantClasses = computed(() => {
  const variants = {
    default:
      'bg-gradient-to-r from-amber-400 via-yellow-400 to-amber-500 text-amber-900 dark:from-amber-500 dark:via-yellow-500 dark:to-amber-600 dark:text-amber-50 shadow-sm',
    subtle:
      'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300 border border-amber-200 dark:border-amber-800',
    outline:
      'bg-transparent text-amber-600 dark:text-amber-400 border-2 border-amber-400 dark:border-amber-500',
  };
  return variants[props.variant];
});

const sizeClasses = computed(() => {
  const sizes = {
    sm: 'text-[10px] px-2 py-0.5',
    md: 'text-xs px-2.5 py-1',
    lg: 'text-sm px-3 py-1.5',
  };
  return sizes[props.size];
});
</script>
