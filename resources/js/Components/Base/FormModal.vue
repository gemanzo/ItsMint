<template>
  <BaseModal
    :is-open="isOpen"
    :title="title"
    :subtitle="subtitle"
    :size="size"
    :closeable="!isLoading"
    @close="handleClose"
  >
    <template #icon>
      <slot name="icon" />
    </template>

    <form @submit.prevent="handleSubmit">
      <div class="space-y-4">
        <slot />
      </div>
    </form>

    <template #footer>
      <div class="flex justify-end space-x-3">
        <SecondaryButton 
          @click="handleClose" 
          :disabled="isLoading"
          type="button"
        >
          {{ cancelText }}
        </SecondaryButton>
        
        <PrimaryButton 
          @click="handleSubmit"
          :disabled="isLoading || !canSubmit"
          :class="{ 'opacity-50 cursor-not-allowed': isLoading || !canSubmit }"
        >
          <svg v-if="isLoading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          {{ isLoading ? loadingText : submitText }}
        </PrimaryButton>
      </div>
    </template>
  </BaseModal>
</template>

<script setup lang="ts">
import BaseModal from './BaseModal.vue'
import PrimaryButton from '../PrimaryButton.vue'
import SecondaryButton from '../SecondaryButton.vue'

interface Props {
  isOpen: boolean
  title: string
  subtitle?: string
  size?: 'sm' | 'md' | 'lg' | 'xl' | '2xl'
  isLoading?: boolean
  canSubmit?: boolean
  submitText?: string
  cancelText?: string
  loadingText?: string
}

const props = withDefaults(defineProps<Props>(), {
  size: 'md',
  isLoading: false,
  canSubmit: true,
  submitText: 'Save',
  cancelText: 'Cancel',
  loadingText: 'Saving...'
})

const emit = defineEmits<{
  close: []
  submit: []
}>()

const handleClose = () => {
  if (!props.isLoading) {
    emit('close')
  }
}

const handleSubmit = () => {
  if (!props.isLoading && props.canSubmit) {
    emit('submit')
  }
}
</script> 