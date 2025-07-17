<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';

defineEmits(['update:modelValue']);

const props = withDefaults(
  defineProps<{
    modelValue?: string;
    type?: string;
    size?: 'sm' | 'md' | 'lg';
    variant?: 'default' | 'error';
  }>(),
  {
    type: 'text',
    size: 'md',
    variant: 'default',
  }
);

const input = ref<HTMLInputElement>();

const inputClasses = computed(() => {
  const baseClasses = [
    'border border-gray-300 dark:border-gray-600',
    'bg-white dark:bg-gray-700',
    'text-gray-900 dark:text-gray-100',
    'rounded-card',
    'focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500',
    'dark:focus:ring-blue-600 dark:focus:border-blue-600',
    'transition-colors duration-200',
    'w-full',
    'touch-target', // Custom class for minimum touch target size
  ];

  // Size classes
  const sizeClasses = {
    sm: 'px-3 py-2 text-sm',
    md: 'px-4 py-3 text-base', // Increased padding for better touch experience
    lg: 'px-4 py-4 text-lg',
  };

  // Variant classes
  const variantClasses = {
    default: [],
    error: [
      'border-red-500 dark:border-red-400',
      'focus:ring-red-500 focus:border-red-500',
      'dark:focus:ring-red-600 dark:focus:border-red-600',
    ],
  };

  return [
    ...baseClasses,
    sizeClasses[props.size],
    ...variantClasses[props.variant],
  ].join(' ');
});

onMounted(() => {
  if (input.value?.hasAttribute('autofocus')) {
    input.value.focus();
  }
});

defineExpose({ focus: () => input.value?.focus() });
</script>

<template>
  <input
    ref="input"
    :class="inputClasses"
    :type="type"
    :value="modelValue"
    @input="$emit('update:modelValue', $event.target.value)"
    v-bind="$attrs"
  />
</template>
