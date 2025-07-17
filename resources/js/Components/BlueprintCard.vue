<template>
  <Link :href="route('blueprints.show', blueprint.id)" :class="cardClasses">
    <!-- Grid View -->
    <template v-if="viewMode === 'grid'">
      <!-- Card Image -->
      <div class="aspect-[3/4] relative overflow-hidden">
        <img
          :src="blueprint.image_url || placeholderImage"
          :alt="blueprint.name"
          class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
          @error="handleImageError"
        />
      </div>

      <!-- Card Content -->
      <div class="p-2.5">
        <!-- Card Name -->
        <h3
          class="font-medium text-xs text-gray-900 dark:text-white mb-1 line-clamp-2 leading-tight"
          :title="blueprint.name"
        >
          {{ blueprint.name }}
        </h3>

        <!-- Expansion & Collector Number -->
        <div v-if="blueprint.expansion" class="mb-1.5 space-y-1">
          <div class="text-xs text-gray-500 dark:text-gray-400 font-normal">
            {{ blueprint.expansion.code }}
            <span
              v-if="blueprint.expansion.name"
              class="text-gray-400 dark:text-gray-500"
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
        <div
          class="mt-1.5 pt-1.5 border-t border-gray-100 dark:border-gray-700"
        >
          <div
            v-if="blueprint.latest_price"
            class="flex items-center justify-between"
          >
            <span class="text-sm font-semibold text-gray-900 dark:text-white">
              €{{ formatPrice(blueprint.latest_price.price_cents) }}
            </span>
            <button
              class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-gray-600 hover:bg-gray-700 transition-colors duration-150"
              title="Add to portfolio"
              @click.prevent="handleAddToPortfolio"
            >
              <svg
                class="w-2.5 h-2.5 text-white"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M12 4v16m8-8H4"
                />
              </svg>
            </button>
          </div>
          <div v-else class="flex items-center justify-between">
            <span class="text-xs text-gray-400 dark:text-gray-500">--</span>
            <button
              class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-gray-300 dark:bg-gray-600 hover:bg-gray-400 dark:hover:bg-gray-500 transition-colors duration-150"
              title="Add to portfolio"
              @click.prevent="handleAddToPortfolio"
            >
              <svg
                class="w-2.5 h-2.5 text-gray-600 dark:text-gray-400"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M12 4v16m8-8H4"
                />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </template>

    <!-- List View -->
    <template v-else>
      <div
        class="flex items-center p-4 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150 group"
      >
        <div class="flex-shrink-0 w-16 h-22 rounded-card overflow-hidden">
          <img
            :src="blueprint.image_url || placeholderImage"
            :alt="blueprint.name"
            class="w-full h-full object-cover"
            @error="handleImageError"
          />
        </div>
        <div class="ml-4 flex-1">
          <div class="flex items-center justify-between">
            <div class="flex-1 min-w-0">
              <h3
                class="text-sm font-medium text-gray-900 dark:text-white truncate"
              >
                {{ blueprint.name }}
              </h3>
              <div class="mt-1 space-y-1">
                <div
                  v-if="blueprint.expansion"
                  class="flex items-center space-x-2"
                >
                  <span class="text-xs text-gray-500 dark:text-gray-400">
                    {{ blueprint.expansion.code }}
                    <span
                      v-if="blueprint.expansion.name"
                      class="text-gray-400 dark:text-gray-500"
                    >
                      • {{ blueprint.expansion.name }}
                    </span>
                  </span>
                  <span
                    v-if="blueprint.latest_price"
                    class="text-sm font-semibold text-gray-900 dark:text-white"
                  >
                    €{{ formatPrice(blueprint.latest_price.price_cents) }}
                  </span>
                </div>
                <div
                  v-if="blueprint.collector_number"
                  class="text-xs text-gray-400 dark:text-gray-500 font-mono"
                >
                  #{{ blueprint.collector_number }}
                </div>
              </div>
            </div>
            <div class="ml-3 flex items-center space-x-2">
              <button
                class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-gray-600 hover:bg-gray-700 transition-colors duration-150"
                title="Add to portfolio"
                @click.prevent="handleAddToPortfolio"
              >
                <svg
                  class="w-2.5 h-2.5 text-white"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 4v16m8-8H4"
                  />
                </svg>
              </button>
              <svg
                class="h-4 w-4 text-gray-400 group-hover:text-gray-600 transition-colors duration-150"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M9 5l7 7-7 7"
                />
              </svg>
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

interface Blueprint {
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
}

interface Props {
  blueprint: Blueprint;
  viewMode: 'grid' | 'list';
}

const props = defineProps<Props>();
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
  const baseClasses = 'group transition-all duration-150';

  if (props.viewMode === 'grid') {
    return `${baseClasses} bg-white dark:bg-gray-800 rounded-card border border-gray-200 dark:border-gray-700 hover:shadow-card-hover overflow-hidden`;
  } else {
    return `${baseClasses} block`;
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
