<template>
  <AuthenticatedLayout>
    <template #header>
      <h2
        class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight"
      >
        Card Scanner
      </h2>
    </template>

    <div class="py-6 sm:py-12">
      <div class="max-w-4xl mx-auto px-3 sm:px-6 lg:px-8">
        <div
          class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg"
        >
          <div class="p-4 sm:p-6 text-gray-900 dark:text-gray-100">
            <!-- Header Section -->
            <div class="text-center mb-6 sm:mb-8">
              <div
                class="mx-auto flex items-center justify-center h-16 w-16 sm:h-20 sm:w-20 rounded-full bg-indigo-100 dark:bg-indigo-900 mb-4"
              >
                <CameraIcon
                  class="h-8 w-8 sm:h-10 sm:w-10 text-indigo-600 dark:text-indigo-400"
                />
              </div>
              <h3 class="text-xl sm:text-2xl leading-6 font-bold">
                Automatic Card Recognition
              </h3>
              <p
                class="mt-2 text-sm sm:text-base text-gray-500 dark:text-gray-400 max-w-2xl mx-auto"
              >
                Use your camera or upload an image to automatically identify and
                add cards to your collection
              </p>
            </div>

            <!-- Action Button -->
            <div class="flex justify-center mb-6 sm:mb-8">
              <button
                class="w-full sm:w-auto inline-flex items-center justify-center px-6 sm:px-8 py-4 sm:py-5 border border-transparent text-base sm:text-lg font-medium rounded-xl shadow-lg text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-4 focus:ring-indigo-500 focus:ring-offset-2 transition-all duration-200 transform active:scale-95"
                @click="openScanModal"
              >
                <CameraIcon class="h-6 w-6 mr-3" />
                Scan New Card
              </button>
            </div>

            <!-- Features Info -->
            <div
              class="mt-8 sm:mt-12 border-t pt-6 sm:pt-8 dark:border-gray-600"
            >
              <h4
                class="text-lg sm:text-xl font-medium text-gray-900 dark:text-gray-100 mb-4 sm:mb-6 text-center"
              >
                How it works
              </h4>
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 sm:gap-8">
                <div class="text-center">
                  <div
                    class="mx-auto flex items-center justify-center h-14 w-14 sm:h-16 sm:w-16 rounded-full bg-blue-100 dark:bg-blue-900 mb-3 sm:mb-4"
                  >
                    <CameraIcon
                      class="h-7 w-7 sm:h-8 sm:w-8 text-blue-600 dark:text-blue-400"
                    />
                  </div>
                  <h5
                    class="text-base sm:text-lg font-medium text-gray-900 dark:text-gray-100"
                  >
                    1. Capture or Upload
                  </h5>
                  <p
                    class="text-sm sm:text-base text-gray-500 dark:text-gray-400 mt-2"
                  >
                    Take a photo or upload an image of your card
                  </p>
                </div>

                <div class="text-center">
                  <div
                    class="mx-auto flex items-center justify-center h-14 w-14 sm:h-16 sm:w-16 rounded-full bg-green-100 dark:bg-green-900 mb-3 sm:mb-4"
                  >
                    <EyeIcon
                      class="h-7 w-7 sm:h-8 sm:w-8 text-green-600 dark:text-green-400"
                    />
                  </div>
                  <h5
                    class="text-base sm:text-lg font-medium text-gray-900 dark:text-gray-100"
                  >
                    2. OCR Recognition
                  </h5>
                  <p
                    class="text-sm sm:text-base text-gray-500 dark:text-gray-400 mt-2"
                  >
                    Our AI reads and identifies the card details
                  </p>
                </div>

                <div class="text-center">
                  <div
                    class="mx-auto flex items-center justify-center h-14 w-14 sm:h-16 sm:w-16 rounded-full bg-purple-100 dark:bg-purple-900 mb-3 sm:mb-4"
                  >
                    <PlusIcon
                      class="h-7 w-7 sm:h-8 sm:w-8 text-purple-600 dark:text-purple-400"
                    />
                  </div>
                  <h5
                    class="text-base sm:text-lg font-medium text-gray-900 dark:text-gray-100"
                  >
                    3. Add to Collection
                  </h5>
                  <p
                    class="text-sm sm:text-base text-gray-500 dark:text-gray-400 mt-2"
                  >
                    Review and add the card to your portfolio
                  </p>
                </div>
              </div>
            </div>

            <!-- Tips Section for Mobile -->
            <div
              class="mt-8 sm:mt-12 bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4 sm:p-6"
            >
              <h4
                class="text-base sm:text-lg font-medium text-gray-900 dark:text-gray-100 mb-3 sm:mb-4"
              >
                📱 Tips for Best Results
              </h4>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                <div class="flex items-start space-x-3">
                  <div
                    class="flex-shrink-0 w-6 h-6 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center mt-0.5"
                  >
                    <svg
                      class="w-3 h-3 text-green-600 dark:text-green-400"
                      fill="currentColor"
                      viewBox="0 0 20 20"
                    >
                      <path
                        fill-rule="evenodd"
                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                        clip-rule="evenodd"
                      />
                    </svg>
                  </div>
                  <p class="text-sm text-gray-600 dark:text-gray-400">
                    Ensure good lighting and focus
                  </p>
                </div>
                <div class="flex items-start space-x-3">
                  <div
                    class="flex-shrink-0 w-6 h-6 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center mt-0.5"
                  >
                    <svg
                      class="w-3 h-3 text-green-600 dark:text-green-400"
                      fill="currentColor"
                      viewBox="0 0 20 20"
                    >
                      <path
                        fill-rule="evenodd"
                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                        clip-rule="evenodd"
                      />
                    </svg>
                  </div>
                  <p class="text-sm text-gray-600 dark:text-gray-400">
                    Place card on flat surface
                  </p>
                </div>
                <div class="flex items-start space-x-3">
                  <div
                    class="flex-shrink-0 w-6 h-6 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center mt-0.5"
                  >
                    <svg
                      class="w-3 h-3 text-green-600 dark:text-green-400"
                      fill="currentColor"
                      viewBox="0 0 20 20"
                    >
                      <path
                        fill-rule="evenodd"
                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                        clip-rule="evenodd"
                      />
                    </svg>
                  </div>
                  <p class="text-sm text-gray-600 dark:text-gray-400">
                    Capture the entire card
                  </p>
                </div>
                <div class="flex items-start space-x-3">
                  <div
                    class="flex-shrink-0 w-6 h-6 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center mt-0.5"
                  >
                    <svg
                      class="w-3 h-3 text-green-600 dark:text-green-400"
                      fill="currentColor"
                      viewBox="0 0 20 20"
                    >
                      <path
                        fill-rule="evenodd"
                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                        clip-rule="evenodd"
                      />
                    </svg>
                  </div>
                  <p class="text-sm text-gray-600 dark:text-gray-400">
                    Avoid shadows and glare
                  </p>
                </div>
              </div>
            </div>

            <!-- Recent Scans (if any) -->
            <div class="mt-8 sm:mt-12">
              <div class="flex items-center justify-between mb-4 sm:mb-6">
                <h4
                  class="text-base sm:text-lg font-medium text-gray-900 dark:text-gray-100"
                >
                  Recent Scans
                </h4>
                <Link
                  :href="route('portfolios.index')"
                  class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 font-medium"
                >
                  View all →
                </Link>
              </div>

              <!-- Placeholder for recent scans -->
              <div
                class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-6 sm:p-8 text-center"
              >
                <svg
                  class="mx-auto h-12 w-12 text-gray-400"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"
                  />
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"
                  />
                </svg>
                <h3
                  class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100"
                >
                  No scans yet
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                  Your scanned cards will appear here
                </p>
                <div class="mt-6">
                  <button
                    type="button"
                    class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    @click="openScanModal"
                  >
                    <CameraIcon class="-ml-1 mr-2 h-5 w-5" />
                    Scan Your First Card
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Card Scan Modal -->
    <CardScanModal
      :show="showScanModal"
      @close="closeScanModal"
      @scan-complete="handleScanComplete"
    />
    <ScanResultsModal
      v-if="showScanResults && currentScan"
      :show="showScanResults"
      :scan="currentScan"
      @close="closeScanResults"
    />
  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CardScanModal from '@/Components/CardScanModal.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { CameraIcon, EyeIcon, PlusIcon } from '@heroicons/vue/24/outline';
import ScanResultsModal from '@/Components/ScanResultsModal.vue';

// State
const showScanModal = ref(false);
const showScanResults = ref(false);
const currentScan = ref(null);

// Methods
const openScanModal = () => {
  showScanModal.value = true;
};

const closeScanModal = () => {
  showScanModal.value = false;
};

const closeScanResults = () => {
  showScanResults.value = false;
  currentScan.value = null;
};

const handleScanComplete = (result: any) => {
  currentScan.value = result;
  showScanModal.value = false;
  showScanResults.value = true;
};
</script>
