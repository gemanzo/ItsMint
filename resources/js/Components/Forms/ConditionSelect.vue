<template>
  <div>
    <label
      v-if="label"
      :for="id"
      class="block text-sm font-medium text-gray-700 dark:text-gray-300"
    >
      {{ label }}
    </label>
    <select
      :id="id"
      :model-value="modelValue"
      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"
      :class="{ 'border-red-300': error }"
      @update:model-value="$emit('update:modelValue', $event)"
    >
      <option
        v-for="condition in conditions"
        :key="condition.value"
        :value="condition.value"
      >
        {{ condition.label }}
      </option>
    </select>
    <p v-if="error" class="text-xs text-red-500 mt-1">
      {{ error }}
    </p>
    <p v-else-if="hint" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
      {{ hint }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { CARD_CONDITIONS } from '@/constants/cardConditions';

interface Props {
  modelValue: string;
  label?: string;
  id?: string;
  error?: string;
  hint?: string;
}

withDefaults(defineProps<Props>(), {
  label: 'Condition',
  id: 'condition',
  error: '',
  hint: '',
});
defineEmits<{
  'update:modelValue': [value: string];
}>();

const conditions = CARD_CONDITIONS;
</script>
