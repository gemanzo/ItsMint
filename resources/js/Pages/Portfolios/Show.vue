<template>
  <AuthenticatedLayout>
    <Head :title="portfolio.name" />

    <!-- Header Section -->
    <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div class="flex-1 min-w-0">
            <nav class="flex mb-4" aria-label="Breadcrumb">
              <ol class="flex items-center space-x-2 text-sm text-gray-500 dark:text-gray-400">
                <li>
                  <a :href="route('portfolios.index')" class="hover:text-gray-700 dark:hover:text-gray-300 transition-colors">
                    Portfolios
                  </a>
                </li>
                <li class="flex items-center">
                  <svg class="h-4 w-4 mx-2 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                  </svg>
                  <span class="font-medium text-gray-900 dark:text-gray-100">{{ portfolio.name }}</span>
                </li>
              </ol>
            </nav>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-2">
              {{ portfolio.name }}
            </h1>
            <p
              v-if="portfolio.description"
              class="text-gray-600 dark:text-gray-400 max-w-2xl"
            >
              {{ portfolio.description }}
            </p>
          </div>
          <div class="flex flex-col sm:flex-row gap-3">
            <button
              class="inline-flex items-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg shadow-sm transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
              @click="openAddCardModal"
            >
              <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
              </svg>
              Add Cards
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="py-8">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="space-y-8">

          <!-- Portfolio Stats -->
          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
              <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Portfolio Overview</h2>
            </div>
            <div class="p-6">
              <div class="stats-grid grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="text-center">
                  <div class="inline-flex items-center justify-center w-12 h-12 bg-blue-100 dark:bg-blue-900/30 rounded-lg mb-3">
                    <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                  </div>
                  <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    {{ portfolio.user_cards?.length || 0 }}
                  </div>
                  <div class="text-sm text-gray-500 dark:text-gray-400">Total Cards</div>
                </div>
                
                <div class="text-center">
                  <div class="inline-flex items-center justify-center w-12 h-12 bg-green-100 dark:bg-green-900/30 rounded-lg mb-3">
                    <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                  </div>
                  <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    {{ formatCurrency(totalEstimatedValue) }}
                  </div>
                  <div class="text-sm text-gray-500 dark:text-gray-400">Estimated Value</div>
                </div>
                
                <div class="text-center">
                  <div class="inline-flex items-center justify-center w-12 h-12 bg-purple-100 dark:bg-purple-900/30 rounded-lg mb-3">
                    <svg class="h-6 w-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                    </svg>
                  </div>
                  <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    {{ formatCurrency(totalPurchaseCost) }}
                  </div>
                  <div class="text-sm text-gray-500 dark:text-gray-400">Purchase Cost</div>
                </div>
                
                <div class="text-center">
                  <div class="inline-flex items-center justify-center w-12 h-12 rounded-lg mb-3"
                       :class="profitLoss >= 0 ? 'bg-emerald-100 dark:bg-emerald-900/30' : 'bg-red-100 dark:bg-red-900/30'">
                    <svg class="h-6 w-6" :class="profitLoss >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path v-if="profitLoss >= 0" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12" />
                      <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6" />
                    </svg>
                  </div>
                  <div class="text-2xl font-bold" :class="profitLoss >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                    {{ formatCurrency(Math.abs(profitLoss)) }}
                  </div>
                  <div class="text-sm text-gray-500 dark:text-gray-400">
                    {{ profitLoss >= 0 ? 'Profit' : 'Loss' }}
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Cards Section -->
          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
              <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                  Cards in Portfolio
                </h2>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                  {{ groupedCards.length }} unique {{ groupedCards.length === 1 ? 'card' : 'cards' }}
                </span>
              </div>
            </div>
            
            <div class="divide-y divide-gray-200 dark:divide-gray-700">
              <!-- Loading State -->
              <div v-if="isLoading" class="p-12 text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-indigo-100 dark:bg-indigo-900/30 rounded-full mb-4">
                  <svg class="animate-spin h-8 w-8 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                </div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">
                  Updating portfolio...
                </h3>
                <p class="text-gray-500 dark:text-gray-400">
                  Please wait while we refresh your cards
                </p>
              </div>
              
              <div
                v-else-if="groupedCards && groupedCards.length > 0"
                class="space-y-0"
              >
                <div
                  v-for="group in groupedCards"
                  :key="group.groupKey"
                  class="card-hover hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150 animate-fade-in"
                >
                  <div class="p-6">
                    <div class="card-content flex items-start space-x-6">
                      <!-- Card Image - Larger -->
                      <div class="card-image flex-shrink-0">
                        <img
                          v-if="group.blueprint?.image_url"
                          :src="group.blueprint.image_url"
                          :alt="group.blueprint.name"
                          class="h-36 w-auto object-contain rounded-lg border-2 border-gray-200 dark:border-gray-600 shadow-sm"
                        />
                        <div
                          v-else
                          class="h-36 w-24 bg-gray-200 dark:bg-gray-600 rounded-lg border-2 border-gray-300 dark:border-gray-500 flex items-center justify-center text-sm text-gray-500 dark:text-gray-400 font-medium"
                        >
                          No Image
                        </div>
                      </div>

                      <!-- Card Info - Better Layout -->
                      <div class="flex-1 min-w-0">
                        <div class="flex justify-between items-start">
                          <!-- Left Column - Card Details -->
                          <div class="flex-1 min-w-0 pr-4">
                            <h3
                              class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2"
                            >
                              {{ group.blueprint?.name }}
                            </h3>

                            <div class="space-y-2">
                              <div class="flex flex-wrap gap-4 text-sm">
                                <span
                                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200"
                                >
                                  {{ group.condition }}
                                </span>
                                <span
                                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200"
                                >
                                  {{ group.language }}
                                </span>
                                <span
                                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200"
                                >
                                  Qty: {{ group.totalQuantity }}
                                </span>
                              </div>

                              <p
                                class="text-sm text-gray-600 dark:text-gray-400"
                              >
                                <span class="font-medium">Acquired:</span>
                                {{
                                  group.acquired_at
                                    ? new Date(
                                        group.acquired_at
                                      ).toLocaleDateString()
                                    : 'N/A'
                                }}
                              </p>

                              <p
                                v-if="group.blueprint?.expansion"
                                class="text-sm text-gray-500 dark:text-gray-500"
                              >
                                {{ group.blueprint.expansion.name }} ({{
                                  group.blueprint.expansion.code
                                }})
                              </p>
                            </div>
                          </div>

                          <!-- Right Column - Pricing Info -->
                          <div class="flex-shrink-0 flex space-x-3">
                            <!-- Single Card Values -->
                            <div
                              class="bg-gray-50 dark:bg-gray-700 p-3 rounded-lg border border-gray-200 dark:border-gray-600 w-36"
                            >
                              <p
                                class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2 text-center"
                              >
                                Per Card
                              </p>
                              <div class="text-center space-y-1">
                                <p
                                  class="text-sm font-bold text-gray-900 dark:text-gray-100"
                                >
                                  {{
                                    formatCurrency(group.singleEstimatedValue)
                                  }}
                                </p>
                                <p
                                  class="text-xs text-gray-600 dark:text-gray-400"
                                >
                                  Est. Value
                                </p>
                                <div
                                  class="border-t border-gray-200 dark:border-gray-600 pt-1 mt-1"
                                >
                                  <p
                                    class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                  >
                                    {{
                                      formatCurrency(group.singlePurchasePrice)
                                    }}
                                  </p>
                                  <p
                                    class="text-xs text-gray-500 dark:text-gray-500"
                                  >
                                    Purchase
                                  </p>
                                </div>
                              </div>
                            </div>

                            <!-- Group Total Values -->
                            <div
                              class="bg-gradient-to-br from-indigo-50 to-blue-50 dark:from-indigo-900/30 dark:to-blue-900/30 p-3 rounded-lg border border-indigo-200 dark:border-indigo-700 w-36"
                            >
                              <p
                                class="text-xs font-medium text-indigo-600 dark:text-indigo-400 mb-2 text-center"
                              >
                                Total (×{{ group.totalQuantity }})
                              </p>
                              <div class="text-center space-y-1">
                                <p
                                  class="text-sm font-bold text-indigo-900 dark:text-indigo-100"
                                >
                                  {{
                                    formatCurrency(
                                      group.estimatedValueCents
                                        ? group.estimatedValueCents / 100
                                        : undefined
                                    )
                                  }}
                                </p>
                                <p
                                  class="text-xs text-indigo-700 dark:text-indigo-300"
                                >
                                  Est. Value
                                </p>
                                <div
                                  class="border-t border-indigo-200 dark:border-indigo-600 pt-1 mt-1"
                                >
                                  <p
                                    class="text-sm font-bold text-indigo-800 dark:text-indigo-200"
                                  >
                                    {{
                                      formatCurrency(
                                        group.purchasePriceCents
                                          ? group.purchasePriceCents / 100
                                          : undefined
                                      )
                                    }}
                                  </p>
                                  <p
                                    class="text-xs text-indigo-600 dark:text-indigo-400"
                                  >
                                    Purchase
                                  </p>
                                </div>
                              </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex flex-col gap-2 min-w-0">
                              <button
                                class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-600 border border-gray-300 dark:border-gray-500 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors duration-150"
                                @click="editCard(getFirstCardFromGroup(group))"
                              >
                                <svg
                                  class="h-4 w-4 mr-1.5"
                                  fill="none"
                                  stroke="currentColor"
                                  viewBox="0 0 24 24"
                                >
                                  <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                                  />
                                </svg>
                                Edit
                              </button>
                              <button
                                class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-red-700 dark:text-red-400 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors duration-150"
                                @click="openRemoveCardModal(group)"
                              >
                                <svg
                                  class="h-4 w-4 mr-1.5"
                                  fill="none"
                                  stroke="currentColor"
                                  viewBox="0 0 24 24"
                                >
                                  <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                  />
                                </svg>
                                Remove
                              </button>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div v-else class="p-12 text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 dark:bg-gray-700 rounded-full mb-4">
                  <svg class="h-8 w-8 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                  </svg>
                </div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">
                  No cards in this portfolio yet
                </h3>
                <p class="text-gray-500 dark:text-gray-400 mb-6">
                  Start building your collection by adding cards to this portfolio.
                </p>
                <button
                  class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg shadow-sm transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                  @click="openAddCardModal"
                >
                  <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                  </svg>
                  Add Your First Card
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <AddCardModal
      v-if="showAddCardModal"
      :portfolio-id="portfolio.id"
      :portfolio-name="portfolio.name"
      @close="closeAddCardModal"
      @card-added="handleCardAddedToCurrentPortfolio"
    />

    <EditCardModal
      v-if="showEditCardModal && cardToEdit"
      :user-card="cardToEdit"
      :portfolio-id="portfolio.id"
      @close="closeEditCardModal"
      @card-updated="handleCardUpdated"
    />

    <RemoveCardModal
      :show="showRemoveCardModal"
      :card-group="cardGroupToRemove"
      @close="closeRemoveCardModal"
      @confirm="confirmRemoveCards"
    />
  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AddCardModal from '@/Components/AddCardModal.vue';
import EditCardModal from '@/Components/EditCardModal.vue';
import RemoveCardModal from '@/Components/RemoveCardModal.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import {
  usePortfolioStore,
  Portfolio as PortfolioInterface,
  UserCard as UserCardInterface,
  Blueprint as BlueprintInterface,
} from '@/stores/portfolioStore';
import { useCurrency } from '@/composables/useCurrency';
import { useToast } from '@/composables/useToast';

type UserCardData = UserCardInterface;

interface PortfolioPageData extends PortfolioInterface {
  user_cards?: UserCardData[];
}

interface CardGroup {
  groupKey: string;
  blueprint?: BlueprintInterface;
  condition: string;
  language: string;
  totalQuantity: number;
  estimatedValueCents: number;
  purchasePriceCents: number;
  acquired_at?: string;
  singleEstimatedValue: number;
  singlePurchasePrice: number;
}

const props = defineProps<{
  portfolio: PortfolioPageData;
}>();

const portfolioStore = usePortfolioStore();
const { formatCurrency } = useCurrency();
const { success: showSuccessToast, error: showErrorToast } = useToast();

const showAddCardModal = ref(false);
const showEditCardModal = ref(false);
const showRemoveCardModal = ref(false);
const cardToEdit = ref<UserCardData | null>(null);
const cardGroupToRemove = ref<CardGroup | null>(null);
const isLoading = ref(false);

const openAddCardModal = () => {
  showAddCardModal.value = true;
};

const closeAddCardModal = () => {
  showAddCardModal.value = false;
};

const handleCardAddedToCurrentPortfolio = () => {
  // Quando una carta è aggiunta al portfolio corrente, la prop `portfolio` non si aggiorna automaticamente.
  // Dobbiamo ricaricare i dati per questa pagina.
  isLoading.value = true;
  router.reload({ 
    only: ['portfolio'],
    onFinish: () => {
      isLoading.value = false;
    }
  });
  // In alternativa, potremmo aggiornare manualmente lo store e sperare che la prop si aggiorni,
  // ma router.reload() è più diretto per le props da Inertia.
};

const editCard = (card: UserCardData) => {
  cardToEdit.value = card;
  showEditCardModal.value = true;
};

const closeEditCardModal = () => {
  showEditCardModal.value = false;
  cardToEdit.value = null;
};

const handleCardUpdated = () => {
  showSuccessToast(
    'Card updated successfully!',
    'Card details have been updated in your portfolio'
  );
  closeEditCardModal();
  isLoading.value = true;
  router.reload({ 
    only: ['portfolio'],
    onFinish: () => {
      isLoading.value = false;
    }
  });
};

const openRemoveCardModal = (group: CardGroup) => {
  cardGroupToRemove.value = group;
  showRemoveCardModal.value = true;
};

const closeRemoveCardModal = () => {
  showRemoveCardModal.value = false;
  cardGroupToRemove.value = null;
};

const confirmRemoveCards = async (quantityToRemove: number) => {
  if (!cardGroupToRemove.value) {
    closeRemoveCardModal();
    return;
  }

  try {
    // Find all cards in this group and remove the specified quantity
    const cardsInGroup = props.portfolio.user_cards?.filter(
      (card) =>
        card.blueprint?.name === cardGroupToRemove.value?.blueprint?.name &&
        card.condition === cardGroupToRemove.value?.condition &&
        card.language === cardGroupToRemove.value?.language
    ) || [];

    // Remove cards one by one until we reach the desired quantity
    let remainingToRemove = quantityToRemove;
    
    for (const card of cardsInGroup) {
      if (remainingToRemove <= 0) break;
      
      const cardQuantity = card.pivot?.quantity || 1;
      const quantityToRemoveFromThisCard = Math.min(remainingToRemove, cardQuantity);
      
      // If we're removing all cards from this specific card entry, remove the entire entry
      if (quantityToRemoveFromThisCard === cardQuantity) {
        await portfolioStore.removeUserCardFromPortfolio(
          props.portfolio.id,
          card.id
        );
      } else {
        // Update the quantity for this card
        await portfolioStore.updateUserCardQuantityInPortfolio(
          props.portfolio.id,
          card.id,
          cardQuantity - quantityToRemoveFromThisCard
        );
      }
      
      remainingToRemove -= quantityToRemoveFromThisCard;
    }
    
    showSuccessToast(
      'Cards removed successfully!',
      `${quantityToRemove} ${quantityToRemove === 1 ? 'copy' : 'copies'} of ${cardGroupToRemove.value.blueprint?.name} ${quantityToRemove === 1 ? 'has' : 'have'} been removed from "${props.portfolio.name}"`
    );
    
    closeRemoveCardModal();
    isLoading.value = true;
    router.reload({ 
      only: ['portfolio'],
      onFinish: () => {
        isLoading.value = false;
      }
    });
  } catch (error) {
    console.error('Error removing cards from portfolio:', error);
    showErrorToast(
      'Error removing cards',
      'Failed to remove cards from portfolio. Please try again.'
    );
    closeRemoveCardModal();
  }
};

const getFirstCardFromGroup = (group: CardGroup): UserCardData => {
  // Find the first card that matches this group
  const card = props.portfolio.user_cards?.find(
    (c) =>
      c.blueprint?.name === group.blueprint?.name &&
      c.condition === group.condition &&
      c.language === group.language &&
      c.purchase_price_cents === (group.singlePurchasePrice ? Math.round(group.singlePurchasePrice * 100) : null)
  );

  if (!card) {
    // Fallback: take the first card with matching name/condition/language
    const fallbackCard = props.portfolio.user_cards?.find(
      (c) =>
        c.blueprint?.name === group.blueprint?.name &&
        c.condition === group.condition &&
        c.language === group.language
    );
    
    if (!fallbackCard) {
      throw new Error('Card not found in group');
    }
    
    return fallbackCard;
  }

  // Create a representative card with the correct total quantity
  const representativeCard: UserCardData = {
    ...card,
    pivot: {
      ...card.pivot,
      quantity: group.totalQuantity // Use the total quantity from the group
    }
  };

  return representativeCard;
};

// formatCurrency now imported from useCurrency() composable

const totalEstimatedValue = computed(() => {
  return (
    props.portfolio.user_cards?.reduce((sum, card) => {
      const value = card.estimated_value_cents
        ? card.estimated_value_cents / 100
        : 0;
      return sum + value * (card.pivot?.quantity || 1);
    }, 0) || 0
  );
});

const totalPurchaseCost = computed(() => {
  return (
    props.portfolio.user_cards?.reduce((sum, card) => {
      const cost = card.purchase_price_cents
        ? card.purchase_price_cents / 100
        : 0;
      return sum + cost * (card.pivot?.quantity || 1);
    }, 0) || 0
  );
});

const profitLoss = computed(() => {
  return totalEstimatedValue.value - totalPurchaseCost.value;
});

const groupedCards = computed(() => {
  const cards = props.portfolio.user_cards || [];
  const grouped: Record<string, CardGroup> = {};

  cards.forEach((card) => {
    const groupKey = `${card.blueprint?.name}-${card.condition}-${card.language}-${card.purchase_price_cents}`;
    if (!grouped[groupKey]) {
      grouped[groupKey] = {
        groupKey,
        blueprint: card.blueprint,
        condition: card.condition,
        language: card.language,
        totalQuantity: 0,
        estimatedValueCents: 0,
        purchasePriceCents: 0,
        acquired_at: card.acquired_at,
        singleEstimatedValue: 0,
        singlePurchasePrice: 0,
      };
    }
    grouped[groupKey].totalQuantity += card.pivot.quantity;
    
    // Calculate total values (price per card × quantity)
    grouped[groupKey].estimatedValueCents += (card.estimated_value_cents || 0) * card.pivot.quantity;
    grouped[groupKey].purchasePriceCents += (card.purchase_price_cents || 0) * card.pivot.quantity;
    
    // Calculate single card values (these remain the same per card)
    grouped[groupKey].singleEstimatedValue = card.estimated_value_cents
      ? card.estimated_value_cents / 100
      : 0;
    grouped[groupKey].singlePurchasePrice = card.purchase_price_cents
      ? card.purchase_price_cents / 100
      : 0;
  });

  return Object.values(grouped);
});
</script>

<style scoped>
/* Smooth transitions for hover effects */
.hover\:bg-gray-50:hover {
  transition: background-color 0.15s ease-in-out;
}

/* Custom animations */
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.animate-fade-in {
  animation: fadeIn 0.3s ease-out;
}

/* Focus styles for better accessibility */
button:focus {
  outline: 2px solid transparent;
  outline-offset: 2px;
}

/* Improved card hover effect */
.card-hover:hover {
  transform: translateY(-1px);
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
  transition: all 0.15s ease-in-out;
}

/* Responsive improvements */
@media (max-width: 640px) {
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
  }
  
  .card-content {
    flex-direction: column;
    space-y: 1rem;
  }
  
  .card-image {
    align-self: center;
    margin-bottom: 1rem;
  }
}
</style>
