<template>
  <div class="relative">
    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
      <svg class="h-4 w-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
      </svg>
    </div>

    <input
      :value="modelValue"
      :placeholder="placeholder"
      :disabled="disabled"
      class="block w-full pl-10 pr-32 py-2.5 text-sm border border-gray-300 dark:border-gray-600 rounded-card placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-gray-500 focus:border-gray-500 bg-white dark:bg-gray-700 transition-colors"
      @input="onInput"
    />

    <div class="absolute inset-y-0 right-0 flex items-center">
      <Listbox v-model="selectedLanguageObject" as="div" class="relative h-full">
        <ListboxButton
          class="relative flex h-full items-center justify-center rounded-md border-0 bg-transparent py-0 pl-2 pr-8 text-gray-500 focus:ring-2 focus:ring-inset focus:ring-indigo-600 dark:text-gray-400 sm:text-sm"
        >
          <span v-if="selectedLanguageObject.countryCode" :class="['fi', `fi-${selectedLanguageObject.countryCode}`]" class="mr-2 h-4 w-6" />
          <svg v-else class="h-5 w-5 text-gray-400 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 21l-5.25-11.25L.75 21m10.5 0H22.5M3.75 12h15M12 12V5.25M15 5.25H9" />
          </svg>
          <span class="block truncate">{{ selectedLanguageObject.name }}</span>
          <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
            <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
              <path fill-rule="evenodd" d="M10 3a.75.75 0 01.53.22l3.5 3.5a.75.75 0 01-1.06 1.06L10 4.81 7.53 7.28a.75.75 0 01-1.06-1.06l3.5-3.5A.75.75 0 0110 3zM10 17a.75.75 0 01-.53-.22l-3.5-3.5a.75.75 0 011.06-1.06L10 15.19l2.47-2.47a.75.75 0 011.06 1.06l-3.5 3.5A.75.75 0 0110 17z" clip-rule="evenodd" />
            </svg>
          </span>
        </ListboxButton>

        <ListboxOptions class="absolute z-50 right-0 mt-1 max-h-56 w-40 overflow-auto rounded-md bg-white dark:bg-gray-700 py-1 text-base shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">
          <ListboxOption v-for="lang in CARD_LANGUAGES" :key="lang.code" v-slot="{ active, selected }" :value="lang" as="template">
            <li :class="[ active ? 'bg-indigo-600 text-white' : 'text-gray-900 dark:text-gray-100', 'relative cursor-default select-none py-2 pl-3 pr-9' ]">
              <div class="flex items-center">
                <span v-if="lang.countryCode" :class="['fi', `fi-${lang.countryCode}`]" class="mr-3 h-4 w-6" />
                <svg v-else class="h-5 w-5 text-gray-400 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 21l-5.25-11.25L.75 21m10.5 0H22.5M3.75 12h15M12 12V5.25M15 5.25H9" />
                </svg>
                <span :class="[selected ? 'font-semibold' : 'font-normal', 'ml-3 block truncate']">{{ lang.name }}</span>
              </div>
              <span v-if="selected" :class="[ active ? 'text-white' : 'text-indigo-600 dark:text-indigo-400', 'absolute inset-y-0 right-0 flex items-center pr-4' ]">
                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                  <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                </svg>
              </span>
            </li>
          </ListboxOption>
        </ListboxOptions>
      </Listbox>

      <div class="pr-3 flex items-center">
        <button v-if="showControls && modelValue.length > 0" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors" @click="$emit('clear')">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
        <div v-else-if="loading" class="animate-spin h-4 w-4 text-gray-400">
          <svg fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
          </svg>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, watch, ref } from 'vue';
import { Listbox, ListboxButton, ListboxOptions, ListboxOption } from '@headlessui/vue';
import { CARD_LANGUAGES } from '@/constants/cardLanguages';

const props = withDefaults(defineProps<{
  modelValue: string;
  language: string; // pass '' to represent Any
  placeholder?: string;
  disabled?: boolean;
  loading?: boolean;
  showControls?: boolean;
}>(), {
  placeholder: 'Search cards...',
  disabled: false,
  loading: false,
  showControls: true,
});

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void;
  (e: 'update:language', value: string): void;
  (e: 'clear'): void;
}>();

const selectedLanguageObject = ref(
  CARD_LANGUAGES.find((l) => l.code === props.language) || CARD_LANGUAGES[0]
);

watch(
  () => props.language,
  (code) => {
    const found = CARD_LANGUAGES.find((l) => l.code === code);
    if (found) selectedLanguageObject.value = found;
  }
);

watch(selectedLanguageObject, (obj) => {
  // Allow empty code ('') for Any
  if (obj && typeof obj.code === 'string') emit('update:language', obj.code);
});

const onInput = (e: Event) => {
  const value = (e.target as HTMLInputElement).value;
  emit('update:modelValue', value);
};
</script>


