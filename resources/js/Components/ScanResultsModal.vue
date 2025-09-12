<template>
  <teleport to="body">
    <div
      v-show="show"
      class="fixed inset-0 z-50 overflow-y-auto"
      aria-labelledby="modal-title"
      role="dialog"
      aria-modal="true"
    >
      <div
        class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0"
      >
        <!-- Background overlay -->
        <div
          v-show="show"
          class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
          aria-hidden="true"
          @click="closeModal"
        />

        <!-- Modal panel -->
        <div
          class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full dark:bg-gray-800"
        >
          <!-- Header -->
          <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 dark:bg-gray-800">
            <div class="sm:flex sm:items-start">
              <div
                class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-green-100 sm:mx-0 sm:h-10 sm:w-10 dark:bg-green-900"
              >
                <CheckCircleIcon
                  v-if="scan?.status === 'completed'"
                  class="h-6 w-6 text-green-600 dark:text-green-400"
                />
                <ExclamationTriangleIcon
                  v-else-if="scan?.status === 'failed'"
                  class="h-6 w-6 text-red-600 dark:text-red-400"
                />
                <ClockIcon
                  v-else
                  class="h-6 w-6 text-yellow-600 dark:text-yellow-400"
                />
              </div>
              <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                <h3
                  id="modal-title"
                  class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100"
                >
                  Scan Results
                </h3>
                <div class="mt-2">
                  <p class="text-sm text-gray-500 dark:text-gray-400">
                    Status:
                    <span :class="getStatusClass()">{{
                      formatStatus(scan?.status || 'unknown')
                    }}</span>
                    <span v-if="scan?.confidence_score" class="ml-2">
                      • Confidence: {{ scan.confidence_score }}%
                    </span>
                  </p>
                </div>
              </div>
              <button
                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                @click="closeModal"
              >
                <XMarkIcon class="h-6 w-6" />
              </button>
            </div>
          </div>

          <!-- Content -->
          <div class="px-4 pb-4 sm:p-6 sm:pb-4 max-h-96 overflow-y-auto">
            <div v-if="scan" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
              <!-- Image Preview -->
              <div>
                <h4
                  class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3"
                >
                  Scanned Image
                </h4>
                <div
                  class="border rounded-lg overflow-hidden dark:border-gray-600"
                >
                  <img
                    v-if="imageUrl"
                    :src="imageUrl"
                    alt="Scanned card"
                    class="w-full h-auto"
                  />
                  <div
                    v-else
                    class="h-48 bg-gray-200 dark:bg-gray-700 flex items-center justify-center"
                  >
                    <span class="text-gray-500 dark:text-gray-400"
                      >Loading image...</span
                    >
                  </div>
                </div>
              </div>

              <!-- Results -->
              <div class="space-y-6">
                <!-- Detected Cards -->
                <div
                  v-if="scan.detected_cards && scan.detected_cards.length > 0"
                >
                  <h4
                    class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3"
                  >
                    Detected Cards
                  </h4>
                  <div class="space-y-3">
                    <div
                      v-for="(card, index) in scan.detected_cards"
                      :key="index"
                      class="border rounded-lg p-3 transition-all duration-200 cursor-pointer dark:border-gray-600"
                      :class="[
                        selectedCard === (card.blueprint_id || 0)
                          ? 'border-indigo-500 bg-indigo-50 dark:border-indigo-400 dark:bg-indigo-900/30 ring-2 ring-indigo-500 ring-opacity-50'
                          : index === 0
                            ? 'border-green-300 bg-green-50 dark:border-green-600 dark:bg-green-900/20'
                            : card.confidence >= 80
                              ? 'border-green-300 hover:border-green-400'
                              : card.confidence >= 60
                                ? 'border-yellow-300 hover:border-yellow-400'
                                : 'border-red-300 hover:border-red-400',
                      ]"
                      @click="selectCard(card.blueprint_id || 0)"
                    >
                      <div class="flex items-start space-x-3">
                        <!-- Card Image -->
                        <div class="flex-shrink-0">
                          <div class="h-20 w-14 flex-shrink-0">
                            <img
                              v-if="card.image_url"
                              :src="card.image_url"
                              :alt="card.name"
                              class="h-20 w-auto object-contain rounded-md border border-gray-200 dark:border-gray-600"
                              @error="handleImageError"
                              @load="() => {}"
                            />
                            <div
                              v-else
                              class="h-20 w-14 bg-gray-200 dark:bg-gray-700 rounded-md border border-gray-300 dark:border-gray-600 flex items-center justify-center"
                            >
                              <span
                                class="text-xs text-gray-500 dark:text-gray-400"
                                >No Image</span
                              >
                            </div>
                          </div>
                        </div>

                        <!-- Card Details -->
                        <div class="flex-1 min-w-0">
                          <div class="flex justify-between items-start">
                            <div class="flex-1">
                              <p
                                class="text-sm font-medium text-gray-900 dark:text-gray-100"
                              >
                                {{ card.name }}
                                <span
                                  v-if="index === 0"
                                  class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200"
                                >
                                  Best Match
                                </span>
                                <span
                                  v-if="
                                    selectedCard === (card.blueprint_id || 0)
                                  "
                                  class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200"
                                >
                                  ✓ Selected
                                </span>
                              </p>
                              <p
                                v-if="card.expansion"
                                class="text-xs text-gray-500 dark:text-gray-400"
                              >
                                {{ card.expansion }}
                                <span v-if="card.card_number" class="ml-1"
                                  >• {{ card.card_number }}</span
                                >
                              </p>
                            </div>
                            <div class="ml-3 text-right flex-shrink-0">
                              <button
                                class="block w-full px-3 py-1 text-xs rounded transition-colors"
                                :class="
                                  selectedCard === (card.blueprint_id || 0)
                                    ? 'bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600'
                                    : 'bg-gray-600 text-white hover:bg-gray-700 dark:bg-gray-500 dark:hover:bg-gray-600'
                                "
                                @click.stop="selectCard(card.blueprint_id || 0)"
                              >
                                {{
                                  selectedCard === (card.blueprint_id || 0)
                                    ? 'Selected'
                                    : 'Select'
                                }}
                              </button>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- No cards detected -->
                <div v-else-if="scan.status === 'completed'">
                  <h4
                    class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3"
                  >
                    No Cards Detected
                  </h4>
                  <div
                    class="border rounded-lg p-4 text-center dark:border-gray-600"
                  >
                    <ExclamationTriangleIcon
                      class="h-8 w-8 text-yellow-500 mx-auto mb-2"
                    />
                    <div class="space-y-2">
                      <p
                        class="text-sm font-medium text-gray-800 dark:text-gray-200"
                      >
                        Card recognition failed
                      </p>
                      <p class="text-xs text-gray-600 dark:text-gray-400">
                        {{
                          scan.message ||
                          'Could not find the expansion code (e.g., OP01-100) in the image.'
                        }}
                      </p>
                      <div
                        class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-600 rounded p-3 mt-3"
                      >
                        <p
                          class="text-xs text-blue-800 dark:text-blue-200 font-medium mb-1"
                        >
                          💡 Tips for better recognition:
                        </p>
                        <ul
                          class="text-xs text-blue-700 dark:text-blue-300 space-y-1"
                        >
                          <li
                            v-for="(suggestion, index) in scan.suggestions ||
                            defaultSuggestions"
                            :key="index"
                          >
                            • {{ suggestion }}
                          </li>
                        </ul>
                      </div>
                      <p class="text-sm text-gray-600 dark:text-gray-400 mt-3">
                        Try taking a better photo or assign the card manually
                        below.
                      </p>
                    </div>
                  </div>
                </div>

                <!-- Manual Card Assignment -->
                <div>
                  <h4
                    class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3"
                  >
                    Manual Assignment
                  </h4>
                  <div class="space-y-3">
                    <div class="relative">
                      <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Type to search... e.g., Luffy OP01-001"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"
                        autocomplete="off"
                        @input="handleSearchInput"
                      />

                      <!-- Search Results Dropdown -->
                      <div
                        v-if="searchQuery.length > 1"
                        class="absolute z-20 mt-1 w-full max-h-72 overflow-auto rounded-md bg-white dark:bg-gray-700 text-base shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm"
                      >
                        <!-- Loading State -->
                        <div
                          v-if="isSearching && searchResults.length === 0"
                          class="p-4 text-center text-sm text-gray-500 dark:text-gray-400"
                        >
                          Searching for cards...
                        </div>

                        <!-- No Results -->
                        <div
                          v-else-if="!isSearching && searchResults.length === 0"
                          class="p-4 text-center text-sm text-gray-500 dark:text-gray-400"
                        >
                          No cards found for "{{ searchQuery }}".
                        </div>

                        <!-- Search Results -->
                        <ul v-else class="py-1">
                          <li
                            v-for="bp in searchResults"
                            :key="bp.id"
                            class="group cursor-pointer select-none relative p-3 text-gray-900 dark:text-gray-200 hover:bg-indigo-600 hover:text-white flex items-center space-x-3 transition-colors duration-150"
                            @click="selectBlueprint(bp)"
                          >
                            <img
                              v-if="bp.image_url"
                              :src="bp.image_url"
                              alt="Card image"
                              class="h-16 w-auto flex-shrink-0 object-contain rounded-sm"
                            />
                            <span
                              v-else
                              class="h-16 w-12 flex-shrink-0 flex items-center justify-center bg-gray-200 dark:bg-gray-600 rounded-sm text-xs text-gray-500 dark:text-gray-400"
                              >No Img</span
                            >

                            <div class="flex-1 min-w-0">
                              <p class="font-medium truncate" :title="bp.name">
                                {{ bp.name }}
                              </p>
                              <p
                                class="text-xs text-gray-500 dark:text-gray-400 group-hover:text-indigo-200 truncate"
                                :title="`${bp.expansion?.name} (${bp.expansion?.code})`"
                              >
                                {{ bp.expansion?.name }} ({{
                                  bp.expansion?.code
                                }})
                              </p>
                            </div>

                            <div
                              v-if="bp.latest_price?.price_cents"
                              class="text-right flex-shrink-0 pl-2"
                            >
                              <p
                                class="font-semibold text-sm text-green-600 dark:text-green-400"
                              >
                                {{
                                  formatCurrency(
                                    bp.latest_price.price_cents / 100
                                  )
                                }}
                              </p>
                              <p
                                class="text-xs text-gray-500 dark:text-gray-400 group-hover:text-indigo-200"
                              >
                                Market
                              </p>
                            </div>
                            <div v-else class="text-right flex-shrink-0 pl-2">
                              <p
                                class="text-xs text-gray-400 dark:text-gray-500"
                              >
                                No price
                              </p>
                            </div>
                          </li>
                          <li
                            v-if="hasMoreResults"
                            class="p-2 text-center sticky bottom-0 bg-white/80 dark:bg-gray-700/80 backdrop-blur-sm"
                          >
                            <button
                              :disabled="isSearching"
                              class="w-full text-indigo-600 dark:text-indigo-400 hover:underline disabled:opacity-50 py-2"
                              @click="loadMoreBlueprints"
                            >
                              {{ isSearching ? 'Loading...' : 'Load More' }}
                            </button>
                          </li>
                        </ul>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Assigned Blueprint -->
                <div v-if="scan.blueprint">
                  <h4
                    class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3"
                  >
                    Assigned Card
                  </h4>
                  <div
                    class="border rounded-lg p-3 bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-600"
                  >
                    <div class="flex items-center space-x-3">
                      <img
                        v-if="scan.blueprint.image_url"
                        :src="scan.blueprint.image_url"
                        :alt="scan.blueprint.name"
                        class="h-16 w-auto object-contain rounded"
                      />
                      <div class="flex-1">
                        <p
                          class="text-sm font-medium text-gray-900 dark:text-gray-100"
                        >
                          {{ scan.blueprint.name }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                          {{ scan.blueprint.expansion?.name }}
                        </p>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Error Message -->
                <div v-if="scan.status === 'failed' && scan.error_message">
                  <h4
                    class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3"
                  >
                    Error
                  </h4>
                  <div
                    class="bg-red-50 border border-red-200 rounded-lg p-3 dark:bg-red-900/20 dark:border-red-600"
                  >
                    <p class="text-sm text-red-700 dark:text-red-300">
                      {{ scan.error_message }}
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div
            class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse dark:bg-gray-700"
          >
            <button
              v-if="scan?.blueprint"
              type="button"
              class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm"
              @click="createUserCard"
            >
              Add to Collection
            </button>
            <button
              v-if="scan?.status === 'failed'"
              type="button"
              class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-yellow-600 text-base font-medium text-white hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 sm:ml-3 sm:w-auto sm:text-sm"
              @click="retryScan"
            >
              Retry Scan
            </button>
            <button
              v-if="
                !scan?.detected_cards?.length && scan?.status === 'completed'
              "
              type="button"
              class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm"
              @click="tryAgain"
            >
              Take Better Photo
            </button>
            <button
              type="button"
              class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm dark:bg-gray-600 dark:text-gray-300 dark:border-gray-500 dark:hover:bg-gray-500"
              @click="closeModal"
            >
              Close
            </button>
          </div>
        </div>
      </div>
    </div>
  </teleport>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import {
  CheckCircleIcon,
  ExclamationTriangleIcon,
  ClockIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline';
import axios from 'axios';
import debounce from 'lodash/debounce';
import { useCurrency } from '@/composables/useCurrency';
import type { CardScan, Blueprint } from '@/types';

interface Props {
  show: boolean;
  scan: CardScan | null;
}

interface Emits {
  (e: 'close'): void;
  (e: 'card-created', card: CardScan): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

const { formatCurrency } = useCurrency();

// Reactive variables
const imageUrl = ref<string | null>(null);
const searchQuery = ref('');
const searchResults = ref<Blueprint[]>([]);
const selectedCard = ref<number | null>(null);
const isSearching = ref(false);
const searchPage = ref(1);
const hasMoreResults = ref(false);

// Default suggestions for fallback
const defaultSuggestions = [
  'Make sure the card code in the bottom-right corner is clearly visible',
  'Avoid shadows and reflections on the card',
  'Take the photo straight-on, not at an angle',
  'Ensure good lighting on the entire card',
];

// Methods
const closeModal = () => {
  emit('close');
};

const formatStatus = (status: string) => {
  const statusMap: { [key: string]: string } = {
    pending: 'Pending',
    processing: 'Processing',
    completed: 'Completed',
    failed: 'Failed',
  };
  return statusMap[status] || status;
};

const getStatusClass = () => {
  const status = props.scan?.status;
  switch (status) {
    case 'completed':
      return 'text-green-600 dark:text-green-400';
    case 'failed':
      return 'text-red-600 dark:text-red-400';
    case 'processing':
      return 'text-yellow-600 dark:text-yellow-400';
    default:
      return 'text-gray-600 dark:text-gray-400';
  }
};

const selectCard = (cardId: number) => {
  selectedCard.value = cardId;
  assignBlueprint(cardId);
};

const assignBlueprint = async (blueprintId: number) => {
  if (!props.scan) return;

  try {
    const response = await axios.put(`/card-scans/${props.scan.id}/blueprint`, {
      blueprint_id: blueprintId,
    });

    // Update the scan object
    Object.assign(props.scan, response.data.scan);

    // Clear search after successful assignment
    searchQuery.value = '';
    searchResults.value = [];
  } catch (error) {
    console.error('Failed to assign blueprint:', error);
  }
};

const searchBlueprints = async (loadMore = false) => {
  if (searchQuery.value.length < 2) {
    searchResults.value = [];
    return;
  }

  if (!loadMore) {
    searchPage.value = 1;
    searchResults.value = [];
  }

  isSearching.value = true;

  try {
    // Use Elasticsearch API
    const response = await axios.get('/blueprints/search', {
      params: {
        q: searchQuery.value,
        from: (searchPage.value - 1) * 10,
        size: 10,
      },
    });

    // Handle Elasticsearch response format
    if (response.data.success && response.data.data) {
      if (loadMore) {
        searchResults.value.push(...response.data.data);
      } else {
        searchResults.value = response.data.data;
      }
      hasMoreResults.value = response.data.pagination.has_more;
    } else {
      searchResults.value = [];
      hasMoreResults.value = false;
    }
  } catch (error) {
    console.error('Search failed:', error);
    searchResults.value = [];
    hasMoreResults.value = false;
  } finally {
    isSearching.value = false;
  }
};

const loadMoreBlueprints = () => {
  if (hasMoreResults.value && !isSearching.value) {
    searchPage.value++;
    searchBlueprints(true);
  }
};

const debouncedSearchBlueprints = debounce(searchBlueprints, 300);

const handleSearchInput = () => {
  debouncedSearchBlueprints();
};

const selectBlueprint = (blueprint: Blueprint) => {
  assignBlueprint(blueprint.id);
  searchQuery.value = `${blueprint.name} - ${blueprint.expansion?.code}`;
  searchResults.value = [];
};

const retryScan = async () => {
  if (!props.scan) return;

  try {
    const response = await axios.post(`/card-scans/${props.scan.id}/retry`);
    Object.assign(props.scan, response.data.scan);
  } catch (error) {
    console.error('Retry failed:', error);
  }
};

const createUserCard = () => {
  // This will open another modal or redirect to add card form
  // For now, we'll emit an event
  if (props.scan) {
    emit('card-created', props.scan);
  }
};

const tryAgain = () => {
  // Close this modal and let user take a new photo
  emit('close');
  // Could emit a specific event to trigger camera again
  // emit('take-new-photo')
};

const handleImageError = (event: Event) => {
  // Log error for debugging
  const img = event.target as HTMLImageElement;
  console.error('Image failed to load:', img?.src, event);

  // Don't hide the image, let the fallback "No Image" div show instead
  if (img) {
    img.style.display = 'none';
  }
};

const loadImage = async () => {
  if (!props.scan?.id) return;

  try {
    // Use the endpoint URL directly as image source
    imageUrl.value = `/api/card-scans/${props.scan.id}/image`;
  } catch (error) {
    console.error('Failed to load image:', error);
  }
};

// Watch for scan changes
watch(
  () => props.scan,
  (newScan) => {
    if (newScan && props.show) {
      
      selectedCard.value = null; // Reset selection when new scan arrives
      searchQuery.value = ''; // Reset search
      searchResults.value = []; // Reset search results
      loadImage();
    }
  },
  { immediate: true }
);

watch(
  () => props.show,
  (newShow) => {
    if (newShow && props.scan) {
      selectedCard.value = null; // Reset selection when modal opens
      searchQuery.value = ''; // Reset search
      searchResults.value = []; // Reset search results
      loadImage();
    }
  }
);

// Watch for changes in search query to auto-clear results when empty
watch(searchQuery, (newValue) => {
  if (newValue.length < 2) {
    searchResults.value = [];
  }
});
</script>
