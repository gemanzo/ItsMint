<template>
  <div>
    <label
      v-if="label"
      :for="id"
      class="block text-sm font-medium text-gray-700 dark:text-gray-300"
    >
      {{ label }}
      <span v-if="required" class="text-red-500">*</span>
    </label>
    <div class="relative">
      <input
        :id="id"
        :value="modelValue"
        type="number"
        step="0.01"
        min="0"
        :placeholder="computedPlaceholder"
        :required="required"
        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 pr-10"
        :class="{
          'border-red-300 focus:border-red-500 focus:ring-red-500': error,
          'border-red-500 focus:border-red-500 focus:ring-red-500':
            error && !modelValue && required,
        }"
        @input="handleInput"
      />
      <span
        class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-gray-500 dark:text-gray-400 text-sm"
      >
        {{ currency }}
      </span>
    </div>

    <p v-if="error" class="text-xs text-red-500 mt-1">
      {{ error }}
    </p>
    <p v-else-if="hint" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
      {{ hint }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useCurrency } from '@/composables/useCurrency';

interface Props {
  modelValue: number | null;
  label?: string;
  id?: string;
  placeholder?: string;
  currency?: string;
  estimatedPrice?: number | null;
  error?: string;
  hint?: string;
  required?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  label: 'Price',
  id: 'price',
  placeholder: 'e.g., 10.50',
  currency: '€',
  estimatedPrice: null,
  error: '',
  hint: '',
  required: false,
});

const emit = defineEmits<{
  'update:modelValue': [value: number | null];
}>();

const { formatCurrency } = useCurrency();

const computedPlaceholder = computed(() => {
  if (props.estimatedPrice !== null && props.estimatedPrice !== undefined) {
    return `e.g., ${formatCurrency(props.estimatedPrice)}`;
  }
  return props.placeholder;
});

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
