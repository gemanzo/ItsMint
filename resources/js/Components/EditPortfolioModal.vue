<template>
  <FormModal
    :is-open="show"
    title="Edit Portfolio"
    subtitle="Update your portfolio details"
    :is-loading="isUpdating"
    :can-submit="isFormValid"
    submit-text="Update Portfolio"
    loading-text="Updating..."
    @close="closeModal"
    @submit="submitEdit"
  >
    <!-- Portfolio Name -->
    <div>
      <label
        for="name"
        class="block text-sm font-medium text-gray-700 dark:text-gray-300"
      >
        Portfolio Name *
      </label>
      <TextInput
        id="name"
        v-model="formData.name"
        type="text"
        required
        class="mt-1 block w-full"
        :class="{
          'border-red-500 focus:border-red-500 focus:ring-red-500': formErrors.name
        }"
        placeholder="Enter portfolio name"
        maxlength="255"
      />
      <p v-if="formErrors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">
        {{ formErrors.name[0] }}
      </p>
    </div>

    <!-- Portfolio Description -->
    <div>
      <label
        for="description"
        class="block text-sm font-medium text-gray-700 dark:text-gray-300"
      >
        Description
      </label>
      <textarea
        id="description"
        v-model="formData.description"
        rows="3"
        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:focus:border-indigo-400 dark:focus:ring-indigo-400"
        :class="{
          'border-red-500 focus:border-red-500 focus:ring-red-500': formErrors.description
        }"
        placeholder="Optional description for your portfolio"
        maxlength="1000"
      />
      <p v-if="formErrors.description" class="mt-1 text-sm text-red-600 dark:text-red-400">
        {{ formErrors.description[0] }}
      </p>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
        {{ formData.description?.length || 0 }}/1000 characters
      </p>
    </div>
  </FormModal>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { usePortfolioStore } from '@/stores/portfolioStore';
import { useToast } from '@/composables/useToast';
import FormModal from '@/Components/Base/FormModal.vue';
import TextInput from '@/Components/TextInput.vue';

interface PortfolioData {
  id: number;
  name: string;
  description?: string;
}

interface Props {
  show: boolean;
  portfolio: PortfolioData | null;
}

const props = defineProps<Props>();
const emit = defineEmits(['close', 'portfolio-updated']);

const { addToast } = useToast();
const portfolioStore = usePortfolioStore();

const isUpdating = ref(false);
const formData = reactive({
  name: '',
  description: ''
});
const formErrors = reactive<Record<string, string[]>>({});

// Form validation
const isFormValid = computed(() => {
  return formData.name.trim().length >= 2 && 
         formData.name.trim().length <= 255 &&
         formData.description.length <= 1000;
});

// Watch for portfolio changes to populate form
watch(() => props.portfolio, (newPortfolio) => {
  if (newPortfolio) {
    formData.name = newPortfolio.name;
    formData.description = newPortfolio.description || '';
    // Clear any previous errors
    Object.keys(formErrors).forEach(key => delete formErrors[key]);
  }
}, { immediate: true });

// Clear form when modal closes
watch(() => props.show, (isShown) => {
  if (!isShown) {
    Object.keys(formErrors).forEach(key => delete formErrors[key]);
  }
});

const closeModal = () => {
  if (!isUpdating.value) {
    emit('close');
  }
};

const submitEdit = async () => {
  if (!props.portfolio || !isFormValid.value || isUpdating.value) return;

  isUpdating.value = true;
  Object.keys(formErrors).forEach(key => delete formErrors[key]);

  try {
    await portfolioStore.updatePortfolio(props.portfolio.id, {
      name: formData.name.trim(),
      description: formData.description.trim() || undefined
    });

    addToast({
      type: 'success',
      title: 'Portfolio updated successfully!',
      message: `"${formData.name}" has been updated.`
    });

    emit('portfolio-updated');
    emit('close');
  } catch (error: any) {
    console.error('Error updating portfolio:', error);
    
    if (error.response?.status === 422 && error.response?.data?.errors) {
      Object.assign(formErrors, error.response.data.errors);
    } else {
      addToast({
        type: 'error',
        title: 'Failed to update portfolio',
        message: error.response?.data?.message || 'An unexpected error occurred. Please try again.'
      });
    }
  } finally {
    isUpdating.value = false;
  }
};
</script> 