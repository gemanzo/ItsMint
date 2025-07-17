<template>
  <div>
    <label
      v-if="label"
      :for="id"
      class="block text-sm font-medium text-gray-700 dark:text-gray-300"
    >
      {{ label }}
      <span
        v-if="showEstimatedHint"
        class="text-xs text-gray-500 dark:text-gray-400 font-normal"
      >
        - Leave empty to use estimated value
      </span>
    </label>
    <div class="relative">
      <input
        :id="id"
        :value="modelValue"
        type="number"
        step="0.01"
        min="0"
        :placeholder="placeholder"
        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 pr-10"
        :class="{ 'border-red-300': error }"
        @input="handleInput"
      />
      <span
        class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-gray-500 dark:text-gray-400 text-sm"
      >
        {{ currency }}
      </span>
    </div>

    <!-- Estimated Price Display -->
    <p
      v-if="showEstimatedPrice && estimatedPrice && !modelValue"
      class="text-xs text-blue-600 dark:text-blue-400 mt-1"
    >
      💡 Will use estimated value: {{ formatCurrency(estimatedPrice) }}
    </p>

    <p v-if="error" class="text-xs text-red-500 mt-1">
      {{ error }}
    </p>
    <p v-else-if="hint" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
      {{ hint }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { useCurrency } from '@/composables/useCurrency';

interface Props {
  modelValue: number | null;
  label?: string;
  id?: string;
  placeholder?: string;
  currency?: string;
  estimatedPrice?: number | null;
  showEstimatedPrice?: boolean;
  showEstimatedHint?: boolean;
  error?: string;
  hint?: string;
}

withDefaults(defineProps<Props>(), {
  label: 'Price',
  id: 'price',
  placeholder: 'e.g., 10.50',
  currency: '€',
  estimatedPrice: null,
  showEstimatedPrice: true,
  showEstimatedHint: true,
  error: '',
  hint: '',
});

const emit = defineEmits<{
  'update:modelValue': [value: number | null];
}>();

const { formatCurrency } = useCurrency();

const handleInput = (event: Event) => {
  const target = event.target as HTMLInputElement;
  const value = target.value;

  // Convert empty string to null, otherwise parse as number
  if (value === '' || value === null || value === undefined) {
    emit('update:modelValue', null);
  } else {
    const numericValue = parseFloat(value);
    // Only emit valid numbers, otherwise null
    emit('update:modelValue', isNaN(numericValue) ? null : numericValue);
  }
};
</script>
