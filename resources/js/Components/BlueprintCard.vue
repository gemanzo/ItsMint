<template>
  <Link :href="route('blueprints.show', blueprint.id)" :class="cardClasses">
    <!-- Grid View -->
    <template v-if="viewMode === 'grid'">
      <!-- Card Image -->
      <div class="aspect-[3/4] relative overflow-hidden rounded-t-card">
        <img
          :src="blueprint.image_url || placeholderImage"
          :alt="blueprint.name"
          class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
          @error="handleImageError"
        />
        <!-- Add to Portfolio FAB for mobile -->
        <button
          class="absolute bottom-2 right-2 w-8 h-8 sm:w-10 sm:h-10 bg-blue-600 hover:bg-blue-700 text-white rounded-full shadow-lg flex items-center justify-center opacity-0 group-hover:opacity-100 sm:opacity-100 transition-all duration-200 touch-target"
          aria-label="Add to portfolio"
          @click.prevent="handleAddToPortfolio"
        >
          <svg
            class="w-4 h-4 sm:w-5 sm:h-5"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M12 6v6m0 0v6m0-6h6m-6 0H6"
            />
          </svg>
        </button>
      </div>

      <!-- Card Content -->
      <div class="p-2 sm:p-3">
        <!-- Card Name -->
        <h3
          class="font-medium text-xs sm:text-sm text-gray-900 dark:text-white mb-1 line-clamp-2 leading-tight"
          :title="blueprint.name"
        >
          {{ blueprint.name }}
        </h3>

        <!-- Expansion & Collector Number -->
        <div v-if="blueprint.expansion" class="mb-2 space-y-1">
          <div class="text-xs text-gray-500 dark:text-gray-400 font-normal">
            {{ blueprint.expansion.code }}
            <span
              v-if="blueprint.expansion.name"
              class="text-gray-400 dark:text-gray-500 hidden sm:inline"
            >
              • {{ blueprint.expansion.name }}
            </span>
          </div>
          <div
            v-if="blueprint.collector_number"
            class="text-xs text-gray-400 dark:text-gray-500 font-mono"
          >
            #{{ blueprint.collector_number }}
          </div>
        </div>

        <!-- Price Section -->
        <div class="mt-2 pt-2 border-t border-gray-100 dark:border-gray-700">
          <div
            v-if="blueprint.latest_price"
            class="flex items-center justify-between"
          >
            <div class="flex-1 min-w-0">
              <p
                class="text-sm sm:text-base font-bold text-green-600 dark:text-green-400 truncate"
              >
                €{{ formatPrice(blueprint.latest_price.price_cents) }}
              </p>
              <div
                class="flex items-center text-xs text-gray-500 dark:text-gray-400 mt-0.5"
              >
                <span>{{ blueprint.latest_price.condition || 'NM' }}</span>
                <span
                  v-if="blueprint.latest_price.language"
                  class="hidden sm:inline"
                >
                  • {{ blueprint.latest_price.language.toUpperCase() }}
                </span>
                <span
                  v-if="blueprint.latest_price.is_foil"
                  class="text-yellow-600 dark:text-yellow-400 ml-1"
                >
                  ✨
                </span>
              </div>
            </div>
          </div>
          <div v-else>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">
              Price not available
            </p>
          </div>
        </div>
      </div>
    </template>

    <!-- List View -->
    <template v-else>
      <div
        class="flex items-center p-3 sm:p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
      >
        <!-- Card Image -->
        <div class="flex-shrink-0 w-12 h-16 sm:w-16 sm:h-20 mr-3 sm:mr-4">
          <img
            :src="blueprint.image_url || placeholderImage"
            :alt="blueprint.name"
            class="w-full h-full object-cover rounded border border-gray-200 dark:border-gray-600"
            @error="handleImageError"
          />
        </div>

        <!-- Card Info -->
        <div class="flex-1 min-w-0">
          <div
            class="flex flex-col sm:flex-row sm:items-start sm:justify-between"
          >
            <div class="flex-1 min-w-0 mb-2 sm:mb-0">
              <h3
                class="text-sm sm:text-base font-medium text-gray-900 dark:text-white truncate"
              >
                {{ blueprint.name }}
              </h3>
              <div
                class="flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400 mt-1"
              >
                <span v-if="blueprint.expansion">{{
                  blueprint.expansion.code
                }}</span>
                <span v-if="blueprint.collector_number"
                  >#{{ blueprint.collector_number }}</span
                >
              </div>
            </div>

            <!-- Price and Actions -->
            <div
              class="flex items-center justify-between sm:flex-col sm:items-end space-x-2 sm:space-x-0"
            >
              <div class="text-right">
                <div v-if="blueprint.latest_price">
                  <p
                    class="text-sm sm:text-base font-bold text-green-600 dark:text-green-400"
                  >
                    €{{ formatPrice(blueprint.latest_price.price_cents) }}
                  </p>
                  <div class="text-xs text-gray-500 dark:text-gray-400">
                    {{ blueprint.latest_price.condition || 'NM' }}
                    <span
                      v-if="blueprint.latest_price.is_foil"
                      class="text-yellow-600 dark:text-yellow-400 ml-1"
                      >✨</span
                    >
                  </div>
                </div>
                <div v-else>
                  <p class="text-xs text-gray-500 dark:text-gray-400">
                    Price not available
                  </p>
                </div>
              </div>

              <!-- Add button -->
              <button
                class="p-2 text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors touch-target"
                aria-label="Add to portfolio"
                @click.prevent="handleAddToPortfolio"
              >
                <svg
                  class="w-5 h-5"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 6v6m0 0v6m0-6h6m-6 0H6"
                  />
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>
    </template>
  </Link>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

// Props definition
const props = defineProps<{
  blueprint: {
    id: number;
    name: string;
    image_url?: string;
    ct_id?: string;
    collector_number?: string;
    expansion?: {
      id: number;
      name: string;
      code: string;
    };
    latest_price?: {
      price_cents: number;
      currency: string;
      condition: string;
      language?: string;
      is_foil?: boolean;
    };
  };
  viewMode: 'grid' | 'list';
}>();

// Emits
const emit = defineEmits(['add-to-portfolio']);

// Placeholder image for cards without images
const placeholderImage =
  'data:image/svg+xml;base64,' +
  btoa(`
<svg width="300" height="420" xmlns="http://www.w3.org/2000/svg">
  <rect width="300" height="420" fill="#f3f4f6"/>
  <rect x="20" y="20" width="260" height="380" fill="#e5e7eb" rx="8"/>
  <circle cx="150" cy="150" r="30" fill="#d1d5db"/>
  <rect x="100" y="200" width="100" height="8" fill="#d1d5db" rx="4"/>
  <rect x="80" y="220" width="140" height="6" fill="#d1d5db" rx="3"/>
  <rect x="90" y="240" width="120" height="6" fill="#d1d5db" rx="3"/>
  <text x="150" y="320" text-anchor="middle" fill="#9ca3af" font-family="Arial" font-size="12">No Image</text>
  <text x="150" y="340" text-anchor="middle" fill="#9ca3af" font-family="Arial" font-size="12">Available</text>
</svg>
`);

// Computed classes for the card container
const cardClasses = computed(() => {
  const baseClasses = 'group transition-all duration-150 block';

  if (props.viewMode === 'grid') {
    return `${baseClasses} bg-white dark:bg-gray-800 rounded-card border border-gray-200 dark:border-gray-700 hover:shadow-card-hover overflow-hidden`;
  } else {
    return `${baseClasses}`;
  }
});

// Format price helper
const formatPrice = (priceCents: number): string => {
  return (priceCents / 100).toFixed(2);
};

// Handle image error
const handleImageError = (event: Event) => {
  (event.target as HTMLImageElement).src = placeholderImage;
};

// Handle add to portfolio button click
const handleAddToPortfolio = () => {
  emit('add-to-portfolio', props.blueprint);
};
</script>

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
