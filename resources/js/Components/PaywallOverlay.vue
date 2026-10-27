<template>
  <div class="absolute inset-0 z-20 flex items-center justify-center">
    <!-- Backdrop with blur -->
    <div
      class="absolute inset-0 bg-white/80 dark:bg-gray-900/80 backdrop-blur-md pointer-events-none"
    />

    <!-- Paywall Card -->
    <div
      class="relative z-10 max-w-md mx-4 p-6 sm:p-8 rounded-2xl shadow-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-center transform transition-all"
    >
      <!-- Premium Badge -->
      <div class="mb-4 flex justify-center">
        <PremiumBadge variant="default" size="lg" force-show />
      </div>

      <!-- Icon -->
      <div class="mb-4 flex justify-center">
        <div
          class="w-16 h-16 rounded-full bg-gradient-to-br from-amber-400 via-yellow-400 to-amber-500 flex items-center justify-center shadow-lg"
        >
          <svg
            class="w-8 h-8 text-amber-900 dark:text-amber-50"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
            />
          </svg>
        </div>
      </div>

      <!-- Title -->
      <h3
        class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white mb-3"
      >
        Premium Feature Locked
      </h3>

      <!-- Description -->
      <p
        class="text-sm sm:text-base text-gray-600 dark:text-gray-300 mb-6 leading-relaxed"
      >
        {{
          message ||
          'Upgrade to Premium to unlock this feature and access all premium capabilities.'
        }}
      </p>

      <!-- Features List (optional) -->
      <div v-if="features && features.length > 0" class="mb-6 text-left">
        <p
          class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wide"
        >
          Premium includes:
        </p>
        <ul class="space-y-2">
          <li
            v-for="feature in features"
            :key="feature"
            class="flex items-start text-sm text-gray-600 dark:text-gray-400"
          >
            <svg
              class="w-5 h-5 text-amber-500 mr-2 flex-shrink-0 mt-0.5"
              fill="currentColor"
              viewBox="0 0 20 20"
            >
              <path
                fill-rule="evenodd"
                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                clip-rule="evenodd"
              />
            </svg>
            <span>{{ feature }}</span>
          </li>
        </ul>
      </div>

      <!-- CTA Button -->
      <a
        :href="upgradeUrl"
        class="inline-flex items-center justify-center px-6 py-3 text-base font-semibold rounded-xl text-white bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
      >
        <svg
          class="w-5 h-5 mr-2"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M13 10V3L4 14h7v7l9-11h-7z"
          />
        </svg>
        Upgrade to Premium
      </a>

      <!-- Optional: Cancel/Close button -->
      <button
        v-if="showClose"
        class="mt-4 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors"
        @click="$emit('close')"
      >
        Maybe later
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import PremiumBadge from './PremiumBadge.vue';

interface Props {
  /**
   * Upgrade URL (default: /subscribe)
   */
  upgradeUrl?: string;
  /**
   * Custom message to display
   */
  message?: string;
  /**
   * List of premium features to highlight
   */
  features?: string[];
  /**
   * Show close button
   */
  showClose?: boolean;
}

withDefaults(defineProps<Props>(), {
  upgradeUrl: '/subscribe',
  message: '',
  features: () => [],
  showClose: false,
});

defineEmits<{
  close: [];
}>();
</script>
