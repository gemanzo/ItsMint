<template>
  <AuthenticatedLayout>
    <Head title="Upgrade to Premium" />
    <div class="min-h-screen bg-gray-50 dark:bg-gray-900 py-10">
      <div class="max-w-3xl mx-auto px-4">
        <!-- Success Message -->
        <div
          v-if="successMessage"
          class="mb-6 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl p-4"
        >
          <div class="flex items-center">
            <svg
              class="h-5 w-5 text-green-600 dark:text-green-400 mr-2"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
            <p class="text-green-800 dark:text-green-200 font-medium">
              {{ successMessage }}
            </p>
          </div>
        </div>

        <!-- Canceled Message -->
        <div
          v-if="canceledMessage"
          class="mb-6 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-xl p-4"
        >
          <div class="flex items-center">
            <svg
              class="h-5 w-5 text-yellow-600 dark:text-yellow-400 mr-2"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
              />
            </svg>
            <p class="text-yellow-800 dark:text-yellow-200 font-medium">
              {{ canceledMessage }}
            </p>
          </div>
        </div>

        <!-- Error Message -->
        <div
          v-if="errorMessage"
          class="mb-6 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl p-4"
        >
          <div class="flex items-center">
            <svg
              class="h-5 w-5 text-red-600 dark:text-red-400 mr-2"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
            <p class="text-red-800 dark:text-red-200 font-medium">
              {{ errorMessage }}
            </p>
          </div>
        </div>

        <div
          class="bg-white dark:bg-gray-800 rounded-2xl shadow border border-gray-200 dark:border-gray-700 p-8"
        >
          <!-- Premium User Status -->
          <div
            v-if="isPremium"
            class="mb-6 pb-6 border-b border-gray-200 dark:border-gray-700"
          >
            <div class="flex items-center justify-between">
              <div>
                <h2
                  class="text-xl font-bold text-gray-900 dark:text-white mb-1"
                >
                  Premium Active
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                  Your subscription is active. Thank you for your support!
                </p>
              </div>
              <div
                class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200"
              >
                Active
              </div>
            </div>
            <div
              v-if="subscription"
              class="mt-4 text-sm text-gray-600 dark:text-gray-400"
            >
              <p v-if="subscription.ends_at">
                Renews:
                {{ new Date(subscription.ends_at).toLocaleDateString() }}
              </p>
            </div>
          </div>

          <!-- Upgrade Section -->
          <div v-else>
            <h1
              class="text-2xl font-bold text-gray-900 dark:text-white mb-2 text-center"
            >
              Upgrade to Premium
            </h1>
            <p class="text-gray-600 dark:text-gray-300 mb-6 text-center">
              Premium unlocks unlimited portfolios, advanced search, export CSV,
              portfolio analytics, and price alerts.
            </p>

            <!-- Features List -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
              <div class="flex items-start space-x-3">
                <svg
                  class="h-5 w-5 text-indigo-600 dark:text-indigo-400 mt-0.5 flex-shrink-0"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M5 13l4 4L19 7"
                  />
                </svg>
                <div>
                  <p class="font-medium text-gray-900 dark:text-white">
                    Unlimited Portfolios
                  </p>
                  <p class="text-sm text-gray-600 dark:text-gray-400">
                    Create as many portfolios as you need
                  </p>
                </div>
              </div>
              <div class="flex items-start space-x-3">
                <svg
                  class="h-5 w-5 text-indigo-600 dark:text-indigo-400 mt-0.5 flex-shrink-0"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M5 13l4 4L19 7"
                  />
                </svg>
                <div>
                  <p class="font-medium text-gray-900 dark:text-white">
                    Advanced Search
                  </p>
                  <p class="text-sm text-gray-600 dark:text-gray-400">
                    Filters, sorting, and up to 50 results
                  </p>
                </div>
              </div>
              <div class="flex items-start space-x-3">
                <svg
                  class="h-5 w-5 text-indigo-600 dark:text-indigo-400 mt-0.5 flex-shrink-0"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M5 13l4 4L19 7"
                  />
                </svg>
                <div>
                  <p class="font-medium text-gray-900 dark:text-white">
                    CSV Export
                  </p>
                  <p class="text-sm text-gray-600 dark:text-gray-400">
                    Export your sales data
                  </p>
                </div>
              </div>
              <div class="flex items-start space-x-3">
                <svg
                  class="h-5 w-5 text-indigo-600 dark:text-indigo-400 mt-0.5 flex-shrink-0"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M5 13l4 4L19 7"
                  />
                </svg>
                <div>
                  <p class="font-medium text-gray-900 dark:text-white">
                    Portfolio Analytics
                  </p>
                  <p class="text-sm text-gray-600 dark:text-gray-400">
                    Charts, ROI tracking, and performance metrics
                  </p>
                </div>
              </div>
              <div class="flex items-start space-x-3">
                <svg
                  class="h-5 w-5 text-indigo-600 dark:text-indigo-400 mt-0.5 flex-shrink-0"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M5 13l4 4L19 7"
                  />
                </svg>
                <div>
                  <p class="font-medium text-gray-900 dark:text-white">
                    Price Alerts
                  </p>
                  <p class="text-sm text-gray-600 dark:text-gray-400">
                    Get notified when card prices change
                  </p>
                </div>
              </div>
              <div class="flex items-start space-x-3">
                <svg
                  class="h-5 w-5 text-indigo-600 dark:text-indigo-400 mt-0.5 flex-shrink-0"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M5 13l4 4L19 7"
                  />
                </svg>
                <div>
                  <p class="font-medium text-gray-900 dark:text-white">
                    Unlimited Cards
                  </p>
                  <p class="text-sm text-gray-600 dark:text-gray-400">
                    No limits on your collection size
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <button
              v-if="!isPremium"
              class="inline-flex items-center justify-center px-5 py-2.5 rounded-md bg-indigo-600 hover:bg-indigo-700 text-white font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              :disabled="isLoading"
              @click="startCheckout"
            >
              <svg
                v-if="isLoading"
                class="animate-spin h-4 w-4 mr-2"
                fill="none"
                viewBox="0 0 24 24"
              >
                <circle
                  class="opacity-25"
                  cx="12"
                  cy="12"
                  r="10"
                  stroke="currentColor"
                  stroke-width="4"
                />
                <path
                  class="opacity-75"
                  fill="currentColor"
                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                />
              </svg>
              <span>{{
                isLoading ? 'Redirecting...' : 'Proceed to Checkout'
              }}</span>
            </button>
            <a
              v-if="isPremium"
              :href="route('subscription.portal')"
              class="inline-flex items-center justify-center px-5 py-2.5 rounded-md bg-gray-600 hover:bg-gray-700 text-white font-medium transition-colors"
            >
              Manage Billing
            </a>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { useFeatures } from '@/composables/useFeatures';

declare const route: any;

const { isPremium } = useFeatures();

const isLoading = ref(false);
const successMessage = ref<string | null>(null);
const canceledMessage = ref<string | null>(null);
const errorMessage = ref<string | null>(null);
const subscription = ref<any>(null);

// Check URL params for success/cancel messages
onMounted(() => {
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('success') === '1') {
    successMessage.value =
      'Payment successful! Your Premium subscription is now active.';
    // Refresh page data to get updated features
    setTimeout(() => {
      window.location.href = '/subscribe';
    }, 2000);
  }
  if (urlParams.get('canceled') === '1') {
    canceledMessage.value = 'Checkout was canceled. You can try again anytime.';
  }

  // Load subscription status if premium
  if (isPremium.value) {
    loadSubscriptionStatus();
  }
});

const loadSubscriptionStatus = async () => {
  try {
    const { data } = await axios.get(route('subscription.status'));
    subscription.value = data.subscription;
  } catch (error) {
    console.error('Failed to load subscription status:', error);
  }
};

const startCheckout = async () => {
  isLoading.value = true;
  errorMessage.value = null;

  try {
    const { data } = await axios.post(route('subscription.checkout'));
    if (data && data.url) {
      window.location.href = data.url;
    } else {
      throw new Error('No checkout URL received');
    }
  } catch (error: any) {
    isLoading.value = false;
    const errorMsg =
      error.response?.data?.error ||
      error.message ||
      'Failed to start checkout. Please try again.';
    errorMessage.value = errorMsg;
  }
};
</script>
