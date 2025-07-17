<template>
  <button
    :type="type"
    :class="buttonClasses"
    :disabled="disabled"
    v-bind="$attrs"
  >
    <slot />
  </button>
</template>

<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
  defineProps<{
    type?: 'button' | 'submit' | 'reset';
    size?: 'sm' | 'md' | 'lg';
    variant?: 'primary' | 'danger' | 'success';
    disabled?: boolean;
    fullWidth?: boolean;
  }>(),
  {
    type: 'button',
    size: 'md',
    variant: 'primary',
    disabled: false,
    fullWidth: false,
  }
);

const buttonClasses = computed(() => {
  const baseClasses = [
    'inline-flex items-center justify-center',
    'font-medium',
    'rounded-button',
    'border border-transparent',
    'transition-all duration-200',
    'focus:outline-none focus:ring-4 focus:ring-offset-2',
    'active:scale-95', // Touch feedback
    'disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none',
    'touch-target', // Custom utility for minimum touch target
  ];

  // Size classes with better touch targets for mobile
  const sizeClasses = {
    sm: 'px-3 py-2 text-sm',
    md: 'px-4 py-3 text-base min-h-touch', // Ensures minimum touch target height
    lg: 'px-6 py-4 text-lg',
  };

  // Variant classes
  const variantClasses = {
    primary: [
      'bg-blue-600 hover:bg-blue-700',
      'text-white',
      'focus:ring-blue-500',
      'dark:bg-blue-700 dark:hover:bg-blue-800',
      'dark:focus:ring-blue-600',
    ],
    danger: [
      'bg-red-600 hover:bg-red-700',
      'text-white',
      'focus:ring-red-500',
      'dark:bg-red-700 dark:hover:bg-red-800',
      'dark:focus:ring-red-600',
    ],
    success: [
      'bg-green-600 hover:bg-green-700',
      'text-white',
      'focus:ring-green-500',
      'dark:bg-green-700 dark:hover:bg-green-800',
      'dark:focus:ring-green-600',
    ],
  };

  // Width classes
  const widthClasses = props.fullWidth ? ['w-full'] : [];

  return [
    ...baseClasses,
    sizeClasses[props.size],
    ...variantClasses[props.variant],
    ...widthClasses,
  ].join(' ');
});
</script>
