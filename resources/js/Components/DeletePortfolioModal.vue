<template>
  <ConfirmationModal
    :show="show"
    title="Delete Portfolio"
    :message="`This action cannot be undone. This will permanently delete the '${portfolioName}' portfolio and all of its data.`"
    type="danger"
    :is-loading="isDeleting"
    :can-confirm="isNameValid"
    confirm-text="Delete Portfolio"
    loading-text="Deleting..."
    @close="close"
    @confirm="handleDelete"
  >
    <div>
      <label 
        for="portfolio-name-confirmation" 
        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
      >
        Type <strong>{{ portfolioName }}</strong> to confirm:
      </label>
      <TextInput
        id="portfolio-name-confirmation"
        ref="nameInput"
        v-model="confirmationText"
        type="text"
        class="block w-full"
        :class="{
          'border-red-500 focus:border-red-500 focus:ring-red-500': confirmationText && !isNameValid,
          'border-green-500 focus:border-green-500 focus:ring-green-500': confirmationText && isNameValid
        }"
        :placeholder="portfolioName"
        :disabled="isDeleting"
      />
    </div>
  </ConfirmationModal>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { usePortfolioStore } from '@/stores/portfolioStore';
import { useToast } from '@/composables/useToast';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import TextInput from '@/Components/TextInput.vue';

interface Props {
  show: boolean;
  portfolioName: string;
  portfolioId: number;
}

const props = defineProps<Props>();
const emit = defineEmits(['close', 'deleted']);

const { addToast } = useToast();
const portfolioStore = usePortfolioStore();

const nameInput = ref<HTMLInputElement>();
const confirmationText = ref('');
const isDeleting = ref(false);

// Computed property to check if the confirmation text matches
const isNameValid = computed(() => {
  return confirmationText.value === props.portfolioName;
});

// Focus input when modal opens
watch(() => props.show, async (newValue) => {
  if (newValue) {
    confirmationText.value = '';
    await nextTick();
    nameInput.value?.focus();
  }
});

const close = () => {
  if (!isDeleting.value) {
    emit('close');
  }
};

const handleDelete = async () => {
  if (!isNameValid.value || isDeleting.value) return;

  isDeleting.value = true;

  try {
    await portfolioStore.deletePortfolio(props.portfolioId);
    
    addToast({
      type: 'success',
      title: 'Portfolio deleted successfully!',
      message: `"${props.portfolioName}" has been permanently deleted.`
    });

    emit('deleted');
    emit('close');
  } catch (error: any) {
    console.error('Error deleting portfolio:', error);
    
    addToast({
      type: 'error',
      title: 'Failed to delete portfolio',
      message: error.response?.data?.message || 'An unexpected error occurred. Please try again.'
    });
  } finally {
    isDeleting.value = false;
  }
};
</script> 