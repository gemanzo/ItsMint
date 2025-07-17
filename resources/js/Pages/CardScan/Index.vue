<template>
  <AuthenticatedLayout>
    <template #header>
      <h2
        class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight"
      >
        Card Scanner
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div
          class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg"
        >
          <div class="p-6 text-gray-900 dark:text-gray-100">
            <!-- Header Section -->
            <div class="text-center mb-8">
              <div
                class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-indigo-100 dark:bg-indigo-900 mb-4"
              >
                <CameraIcon
                  class="h-6 w-6 text-indigo-600 dark:text-indigo-400"
                />
              </div>
              <h3 class="text-lg leading-6 font-medium">
                Automatic Card Recognition
              </h3>
              <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Use your camera or upload an image to automatically identify and
                add cards to your collection
              </p>
            </div>

            <!-- Action Button -->
            <div class="flex justify-center mb-8">
              <button
                class="inline-flex items-center justify-center px-8 py-4 border border-transparent text-lg font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-200"
                @click="openScanModal"
              >
                <CameraIcon class="h-6 w-6 mr-3" />
                Scan New Card
              </button>
            </div>

            <!-- Features Info -->
            <div class="mt-12 border-t pt-8 dark:border-gray-600">
              <h4
                class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4"
              >
                How it works
              </h4>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="text-center">
                  <div
                    class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 dark:bg-blue-900 mb-3"
                  >
                    <CameraIcon
                      class="h-6 w-6 text-blue-600 dark:text-blue-400"
                    />
                  </div>
                  <h5
                    class="text-sm font-medium text-gray-900 dark:text-gray-100"
                  >
                    1. Capture or Upload
                  </h5>
                  <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Take a photo or upload an image of your card
                  </p>
                </div>

                <div class="text-center">
                  <div
                    class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100 dark:bg-green-900 mb-3"
                  >
                    <EyeIcon
                      class="h-6 w-6 text-green-600 dark:text-green-400"
                    />
                  </div>
                  <h5
                    class="text-sm font-medium text-gray-900 dark:text-gray-100"
                  >
                    2. OCR Recognition
                  </h5>
                  <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Our AI reads and identifies the card details
                  </p>
                </div>

                <div class="text-center">
                  <div
                    class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-purple-100 dark:bg-purple-900 mb-3"
                  >
                    <PlusIcon
                      class="h-6 w-6 text-purple-600 dark:text-purple-400"
                    />
                  </div>
                  <h5
                    class="text-sm font-medium text-gray-900 dark:text-gray-100"
                  >
                    3. Add to Collection
                  </h5>
                  <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Review and add the card to your portfolio
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Scan Modal -->
    <CardScanModal
      :show="showScanModal"
      @close="closeScanModal"
      @scan-complete="handleScanComplete"
    />

    <!-- Scan Results Modal -->
    <ScanResultsModal
      :show="showScanResults"
      :scan="currentScan"
      @close="closeScanResults"
      @card-created="handleCardCreated"
    />

    <!-- Quick Add Card Modal -->
    <QuickAddCardModal
      v-if="showQuickAddModal && currentScan?.blueprint"
      :blueprint="currentScan.blueprint"
      @close="closeQuickAddModal"
      @card-added="handleCardAddedFromScan"
    />
  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CardScanModal from '@/Components/CardScanModal.vue';
import ScanResultsModal from '@/Components/ScanResultsModal.vue';
import QuickAddCardModal from '@/Components/QuickAddCardModal.vue';
import { CameraIcon, EyeIcon, PlusIcon } from '@heroicons/vue/24/outline';
import { useToast } from '@/composables/useToast';
import type { CardScan } from '@/types';

// Toast system
const { success: showSuccessToast, error: showErrorToast } = useToast();

// Reactive state
const showScanModal = ref(false);
const showScanResults = ref(false);
const showQuickAddModal = ref(false);
const currentScan = ref<CardScan | null>(null);

// Methods
const openScanModal = () => {
  showScanModal.value = true;
};

const closeScanModal = () => {
  showScanModal.value = false;
};

const handleScanComplete = (scan: CardScan) => {
  currentScan.value = scan;
  showScanModal.value = false;
  showScanResults.value = true;
};

const closeScanResults = () => {
  showScanResults.value = false;
  currentScan.value = null;
};

const closeQuickAddModal = () => {
  showQuickAddModal.value = false;
  currentScan.value = null;
};

const handleCardCreated = (scan: CardScan) => {
  // Close scan results modal
  showScanResults.value = false;

  // If scan has an assigned blueprint, open QuickAddCardModal
  if (scan.blueprint) {
    currentScan.value = scan;
    showQuickAddModal.value = true;
  } else {
    // Show error if no blueprint is assigned
    showErrorToast(
      'No card assigned',
      'Please assign a card before adding to your collection'
    );
  }
};

const handleCardAddedFromScan = () => {
  // QuickAddCardModal already displays the success toast,
  // so nothing is needed here
  closeQuickAddModal();
};
</script>
