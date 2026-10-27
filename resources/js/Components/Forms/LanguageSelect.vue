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
      :value="modelValue"
      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"
      :class="{ 'border-red-300': error }"
      @change="emitSelectedValue"
    >
      <option
        v-for="language in languageOptions"
        :key="language.code || 'any'"
        :value="language.code || language.name"
      >
        {{ language.name }}
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
import { computed } from 'vue';
import {
  CARD_LANGUAGES,
  AVAILABLE_LANGUAGES,
  type CardLanguage,
} from '@/constants/cardLanguages';

interface Props {
  modelValue: string;
  label?: string;
  id?: string;
  error?: string;
  hint?: string;
  includeAny?: boolean;
  /**
   * Language codes actually available for the card (e.g. ['en', 'jp']).
   * When provided, only these languages can be selected.
   */
  availableLanguageCodes?: string[];
}

const props = withDefaults(defineProps<Props>(), {
  label: 'Language',
  id: 'language',
  error: '',
  hint: '',
  includeAny: false,
  availableLanguageCodes: undefined,
});

const emit = defineEmits<{
  'update:modelValue': [value: string];
}>();

const languageOptions = computed<CardLanguage[]>(() => {
  if (props.availableLanguageCodes && props.availableLanguageCodes.length > 0) {
    return props.availableLanguageCodes.map((languageCode) => {
      const knownLanguage = AVAILABLE_LANGUAGES.find(
        (language) => language.code === languageCode
      );
      if (knownLanguage) {
        return knownLanguage;
      }
      // Language not in our list yet: still selectable, labelled with its code
      return {
        name: languageCode.toUpperCase(),
        code: languageCode,
        countryCode: 'un',
      };
    });
  }

  if (props.includeAny) {
    return CARD_LANGUAGES;
  }
  return AVAILABLE_LANGUAGES;
});

// A native <select> never emits "update:modelValue": forward its "change" event
const emitSelectedValue = (event: Event) => {
  emit('update:modelValue', (event.target as HTMLSelectElement).value);
};
</script>
