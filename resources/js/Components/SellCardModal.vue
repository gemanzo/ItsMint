<template>
  <BaseModal :is-open="show" @close="$emit('close')">
    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
      <div class="sm:flex sm:items-start">
        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
          <h3
            class="text-lg leading-6 font-medium text-gray-900 dark:text-white"
          >
            Sell Cards
          </h3>
          <div class="mt-2 space-y-4">
            <div>
              <label
                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                >Quantity</label
              >
              <input
                v-model.number="form.quantity"
                type="number"
                :min="1"
                :max="maxQty"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
              />
              <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Available: {{ maxQty }}
              </p>
            </div>
            <div>
              <label
                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                >Unit sale price (€)</label
              >
              <input
                v-model.number="form.unitSalePrice"
                type="number"
                step="0.01"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
              />
            </div>
            <div>
              <label
                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                >Platform</label
              >
              <select
                v-model="form.platform"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
              >
                <option value="ebay">eBay</option>
                <option value="cardmarket">Cardmarket</option>
                <option value="private">Private</option>
                <option value="cardtrader">CardTrader</option>
                <option value="custom">Custom</option>
              </select>
            </div>
            <div>
              <label
                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                >Sold at</label
              >
              <input
                v-model="form.soldAt"
                type="date"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
              />
            </div>
            <div>
              <label
                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                >Notes</label
              >
              <textarea
                v-model="form.notes"
                rows="3"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <div
      class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse"
    >
      <button
        type="button"
        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-emerald-600 text-base font-medium text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
        :disabled="submitting"
        @click="submit"
      >
        <svg
          v-if="submitting"
          class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
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
            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
          />
        </svg>
        Confirm
      </button>
      <button
        type="button"
        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
        :disabled="submitting"
        @click="$emit('close')"
      >
        Cancel
      </button>
    </div>
  </BaseModal>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import axios from 'axios';
import BaseModal from '@/Components/Base/BaseModal.vue';
// Ziggy's route helper is available globally
declare const route: any;

const props = defineProps<{
  show: boolean;
  portfolioId: number;
  userCardId: number | null;
  maxQuantity: number;
}>();

const emit = defineEmits<{ (e: 'close'): void; (e: 'sold'): void }>();

const form = reactive({
  quantity: 1,
  unitSalePrice: 0,
  platform: 'private',
  soldAt: new Date().toISOString().slice(0, 10),
  notes: '',
});
const submitting = ref(false);
const maxQty = computed(() => props.maxQuantity || 1);

watch(
  () => props.show,
  (s) => {
    if (s) {
      form.quantity = 1;
      submitting.value = false;
    }
  }
);

const submit = async () => {
  if (!props.userCardId) return;
  submitting.value = true;
  try {
    await axios.post(
      route('api.portfolios.sell', { portfolio: props.portfolioId }),
      {
        user_card_id: props.userCardId,
        quantity: form.quantity,
        unit_sale_price_cents: Math.round((form.unitSalePrice || 0) * 100),
        platform: form.platform,
        notes: form.notes || null,
        sold_at: form.soldAt,
      }
    );
    emit('sold');
  } catch (e) {
    console.error('sell failed', e);
  } finally {
    submitting.value = false;
  }
};
</script>
