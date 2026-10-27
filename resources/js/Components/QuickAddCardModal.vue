<script setup lang="ts">
import { ref, reactive, onMounted, computed, watch } from 'vue';
import axios from 'axios';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import ConditionSelect from '@/Components/Forms/ConditionSelect.vue';
import LanguageSelect from '@/Components/Forms/LanguageSelect.vue';
import { useToast } from '@/composables/useToast';
import { todayAsLocalDateString } from '@/utils/date';

interface Blueprint {
  id: number;
  name: string;
  latest_price?: {
    price_cents: number;
    condition: string;
    language?: string;
  };
  available_languages?: string[];
}

interface Portfolio {
  id: number;
  name: string;
}

interface FormData {
  portfolio_id: number | null;
  condition: string;
  language: string;
  quantity: number;
  purchase_price_cents: number;
  acquired_at: string;
  is_foil: boolean;
  errors: Record<string, string>;
}

const props = defineProps<{
  blueprint: Blueprint;
}>();

const emit = defineEmits(['close', 'card-added']);

const { success, error } = useToast();

const portfolios = ref<Portfolio[]>([]);
const isLoadingPortfolios = ref(false);
const detailedBlueprint = ref<Blueprint | null>(null);
const isLoadingBlueprint = ref(false);

const form = reactive<FormData>({
  portfolio_id: null,
  // Condition codes (NM, LP, ...) as listed by ConditionSelect
  condition: 'NM',
  language: 'en',
  quantity: 1,
  purchase_price_cents: props.blueprint.latest_price?.price_cents ?? 0,
  // Required by the API: defaults to today, the user can change it
  acquired_at: todayAsLocalDateString(),
  is_foil: false,
  errors: {},
});

// Acquisition dates in the future are not allowed
const todayDate = todayAsLocalDateString();

const purchasePriceEuros = computed({
  get: () => (form.purchase_price_cents / 100).toString(),
  set: (value: string | number) => {
    const numValue = parseFloat(String(value));
    if (!isNaN(numValue)) {
      form.purchase_price_cents = Math.round(numValue * 100);
    } else {
      form.purchase_price_cents = 0;
    }
  },
});

const quantityString = computed({
  get: () => form.quantity.toString(),
  set: (value: string | number) => {
    const numValue = parseInt(String(value));
    if (!isNaN(numValue) && numValue > 0) {
      form.quantity = numValue;
    } else {
      form.quantity = 1;
    }
  },
});

const fetchPortfolios = async () => {
  isLoadingPortfolios.value = true;
  try {
    const response = await axios.get(route('api.portfolios.index'));
    portfolios.value = response.data.data || response.data;
    if (portfolios.value.length > 0) {
      form.portfolio_id = portfolios.value[0].id;
    }
  } catch (err) {
    console.error('Failed to fetch portfolios:', err);
    error('Error', 'Failed to load portfolios.');
  } finally {
    isLoadingPortfolios.value = false;
  }
};

const fetchBlueprintDetails = async () => {
  isLoadingBlueprint.value = true;
  try {
    const response = await axios.get(
      route('api.blueprints.show', { blueprint: props.blueprint.id })
    );
    detailedBlueprint.value = response.data.data;
    if (detailedBlueprint.value?.available_languages?.length) {
      form.language = detailedBlueprint.value.available_languages.includes('en')
        ? 'en'
        : detailedBlueprint.value.available_languages[0];
    }
  } catch (err) {
    console.error('Failed to fetch blueprint details:', err);
    error('Error', 'Could not load card languages.');
  } finally {
    isLoadingBlueprint.value = false;
  }
};

const submit = () => {
  form.errors = {};
  axios
    .post(
      route('api.portfolios.addBlueprint', { portfolio: form.portfolio_id }),
      {
        blueprint_id: props.blueprint.id,
        ...form,
      }
    )
    .then((response) => {
      success('Card added!', `"${props.blueprint.name}" added to portfolio.`);
      emit('card-added', response.data);
      emit('close');
    })
    .catch((err) => {
      if (err.response && err.response.status === 422) {
        form.errors = err.response.data.errors;
        error('Validation Error', 'Please check the form fields.');
      } else {
        error('Error', 'An unexpected error occurred.');
      }
    });
};

onMounted(() => {
  fetchPortfolios();
  fetchBlueprintDetails();
});
</script>

<template>
  <Modal :show="true" @close="$emit('close')">
    <div class="p-6">
      <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
        Add "{{ blueprint.name }}"
      </h2>
      <div v-if="isLoadingBlueprint" class="text-center py-4">
        <p>Loading card details...</p>
      </div>
      <form v-else class="mt-6 space-y-4" @submit.prevent="submit">
        <div>
          <InputLabel for="portfolio" value="Portfolio" />
          <select
            id="portfolio"
            v-model="form.portfolio_id"
            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
            :disabled="isLoadingPortfolios"
          >
            <option v-if="isLoadingPortfolios" value="" disabled>
              Loading portfolios...
            </option>
            <option v-else-if="portfolios.length === 0" value="" disabled>
              No portfolios found
            </option>
            <option v-else value="" disabled>Select a portfolio</option>
            <option
              v-for="portfolio in portfolios"
              :key="portfolio.id"
              :value="portfolio.id"
            >
              {{ portfolio.name }}
            </option>
          </select>
          <InputError class="mt-2" :message="form.errors.portfolio_id" />
        </div>

        <div>
          <ConditionSelect
            id="condition"
            v-model="form.condition"
            class="mt-1 block w-full"
          />
          <InputError class="mt-2" :message="form.errors.condition" />
        </div>

        <div v-if="detailedBlueprint?.available_languages">
          <LanguageSelect
            id="language"
            v-model="form.language"
            :available-language-codes="detailedBlueprint.available_languages"
            class="mt-1 block w-full"
          />
          <InputError class="mt-2" :message="form.errors.language" />
        </div>

        <div>
          <InputLabel for="quantity" value="Quantity" />
          <TextInput
            id="quantity"
            v-model="quantityString"
            type="number"
            class="mt-1 block w-full"
            min="1"
          />
          <InputError class="mt-2" :message="form.errors.quantity" />
        </div>

        <div>
          <InputLabel for="purchase_price" value="Purchase Price (€)" />
          <TextInput
            id="purchase_price"
            v-model="purchasePriceEuros"
            type="number"
            class="mt-1 block w-full"
            min="0"
            step="0.01"
          />
          <InputError
            class="mt-2"
            :message="form.errors.purchase_price_cents"
          />
        </div>

        <div>
          <InputLabel for="acquired_at" value="Acquired on" />
          <TextInput
            id="acquired_at"
            v-model="form.acquired_at"
            name="acquired_at"
            type="date"
            class="mt-1 block w-full"
            :max="todayDate"
          />
          <InputError class="mt-2" :message="form.errors.acquired_at" />
        </div>

        <div class="flex items-center justify-end mt-6">
          <SecondaryButton @click="$emit('close')">Cancel</SecondaryButton>
          <PrimaryButton class="ms-3" type="submit">Add Card</PrimaryButton>
        </div>
      </form>
    </div>
  </Modal>
</template>
