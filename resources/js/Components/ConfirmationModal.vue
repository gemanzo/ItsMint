<template>
    <Modal :show="show" @close="close">
        <div class="p-6 bg-white dark:bg-gray-800 rounded-lg">
            <div class="flex items-start">
                <div class="flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900/30 sm:mx-0 sm:h-10 sm:w-10">
                    <svg class="h-6 w-6 text-red-600 dark:text-red-400" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                    <h3 class="text-lg leading-6 font-bold text-gray-900 dark:text-gray-100" id="modal-title">
                        {{ title }}
                    </h3>
                    <div class="mt-2">
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            {{ message }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end space-x-3">
                <SecondaryButton @click="close">
                    Cancel
                </SecondaryButton>
                <DangerButton @click="confirm">
                    Confirm
                </DangerButton>
            </div>
        </div>
    </Modal>
</template>

<script setup lang="ts">
import Modal from '@/Components/Modal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

defineProps<{
    show: boolean;
    title: string;
    message: string;
}>();

const emit = defineEmits(['close', 'confirm']);

const close = () => {
    emit('close');
};

const confirm = () => {
    emit('confirm');
};
</script> 