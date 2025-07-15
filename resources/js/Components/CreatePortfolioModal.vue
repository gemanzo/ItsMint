<template>
  <FormModal
    :is-open="isOpen"
    title="Create New Portfolio"
    subtitle="Enter the details for your new portfolio"
    :is-loading="isLoading"
    :can-submit="isFormValid"
    submit-text="Create Portfolio"
    loading-text="Creating..."
    @close="closeModal"
    @submit="submitCreatePortfolio"
  >
    <div>
      <label
        for="portfolioName"
        class="block text-sm font-medium text-gray-700 dark:text-gray-300"
      >
        Portfolio Name *
      </label>
      <TextInput
        id="portfolioName"
        v-model="portfolioDetails.name"
        type="text"
        required
        class="mt-1 block w-full"
        :class="{
          'border-red-500 focus:border-red-500 focus:ring-red-500': errors.name
        }"
        placeholder="Enter portfolio name"
        maxlength="255"
      />
      <p v-if="errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">
        {{ errors.name[0] }}
      </p>
    </div>

    <div>
      <label
        for="portfolioDescription"
        class="block text-sm font-medium text-gray-700 dark:text-gray-300"
      >
        Description
      </label>
      <textarea
        id="portfolioDescription"
        v-model="portfolioDetails.description"
        rows="3"
        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:focus:border-indigo-400 dark:focus:ring-indigo-400"
        :class="{
          'border-red-500 focus:border-red-500 focus:ring-red-500': errors.description
        }"
        placeholder="Optional description for your portfolio"
        maxlength="1000"
      />
      <p v-if="errors.description" class="mt-1 text-sm text-red-600 dark:text-red-400">
        {{ errors.description[0] }}
      </p>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
        {{ portfolioDetails.description?.length || 0 }}/1000 characters
      </p>
    </div>
  </FormModal>
</template>

<script setup lang="ts">
import { computed, reactive, watch, ref } from 'vue';
import { usePortfolioStore } from '@/stores/portfolioStore';
import { useToast } from '@/composables/useToast';
import FormModal from '@/Components/Base/FormModal.vue';
import TextInput from '@/Components/TextInput.vue';

interface Props {
  isOpen: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits(['close', 'created']);

const { addToast } = useToast();
const portfolioStore = usePortfolioStore();

const portfolioDetails = reactive({
  name: '',
  description: ''
});

const errors = reactive<Record<string, string[]>>({});
const isLoading = ref(false);

// Form validation
const isFormValid = computed(() => {
  return portfolioDetails.name.trim().length >= 2 && 
         portfolioDetails.name.trim().length <= 255 &&
         portfolioDetails.description.length <= 1000;
});

// Clear form when modal opens/closes
watch(() => props.isOpen, (newValue) => {
  if (newValue) {
    // Reset form when opening
    portfolioDetails.name = '';
    portfolioDetails.description = '';
    Object.keys(errors).forEach(key => delete errors[key]);
  }
});

const closeModal = () => {
  if (!isLoading.value) {
    emit('close');
  }
};

const submitCreatePortfolio = async () => {
  if (!isFormValid.value || isLoading.value) return;

  isLoading.value = true;
  Object.keys(errors).forEach(key => delete errors[key]);

  try {
    await portfolioStore.createPortfolio(
      portfolioDetails.name.trim(),
      portfolioDetails.description.trim() || undefined
    );

    addToast({
      type: 'success',
      title: 'Portfolio created successfully!',
      description: `"${portfolioDetails.name}" has been added to your portfolios.`
    });

    emit('created');
    emit('close');
  } catch (error: any) {
    console.error('Error creating portfolio:', error);
    
    if (error.response?.status === 422 && error.response?.data?.errors) {
      Object.assign(errors, error.response.data.errors);
    } else {
      addToast({
        type: 'error',
        title: 'Failed to create portfolio',
        description: error.response?.data?.message || 'An unexpected error occurred. Please try again.'
      });
    }
  } finally {
    isLoading.value = false;
  }
};
</script>
