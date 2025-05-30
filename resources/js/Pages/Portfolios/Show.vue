<template>
    <AuthenticatedLayout>
        <Head :title="portfolio.name" />

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <div class="flex justify-between items-center mb-4">
                            <div>
                                <h1 class="text-3xl font-semibold">{{ portfolio.name }}</h1>
                                <p v-if="portfolio.description" class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ portfolio.description }}</p>
                            </div>
                            <button 
                                @click="openAddCardModal"
                                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg shadow">
                                + Add Cards to this Portfolio
                            </button>
                        </div>

                        <!-- Summary Section -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                            <div class="bg-gray-100 dark:bg-gray-700 p-4 rounded-lg shadow">
                                <h3 class="text-md font-semibold mb-1">Total Cards</h3>
                                <p class="text-2xl font-bold">{{ portfolio.user_cards?.length || 0 }}</p>
                            </div>
                            <div class="bg-gray-100 dark:bg-gray-700 p-4 rounded-lg shadow">
                                <h3 class="text-md font-semibold mb-1">Estimated Value</h3>
                                <p class="text-2xl font-bold">{{ formatCurrency(totalEstimatedValue) }}</p>
                            </div>
                            <div class="bg-gray-100 dark:bg-gray-700 p-4 rounded-lg shadow">
                                <h3 class="text-md font-semibold mb-1">Total Purchase Cost</h3>
                                <p class="text-2xl font-bold">{{ formatCurrency(totalPurchaseCost) }}</p>
                            </div>
                        </div>

                        <!-- Cards List -->
                        <div>
                            <h2 class="text-xl font-semibold mb-4">Cards in Portfolio</h2>
                            <div v-if="groupedCards && groupedCards.length > 0" class="space-y-6">
                                <div v-for="group in groupedCards" :key="group.groupKey" class="bg-white dark:bg-gray-800 rounded-xl shadow-md border border-gray-200 dark:border-gray-700 overflow-hidden">
                                    <div class="p-6">
                                        <div class="flex items-start space-x-6">
                                            <!-- Card Image - Larger -->
                                            <div class="flex-shrink-0">
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
                                                        <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">{{ group.blueprint?.name }}</h3>
                                                        
                                                        <div class="space-y-2">
                                                            <div class="flex flex-wrap gap-4 text-sm">
                                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                                    {{ group.condition }}
                                                                </span>
                                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                                    {{ group.language }}
                                                                </span>
                                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                                                                    Qty: {{ group.totalQuantity }}
                                                                </span>
                                                            </div>
                                                            
                                                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                                                <span class="font-medium">Acquired:</span> {{ group.acquired_at ? new Date(group.acquired_at).toLocaleDateString() : 'N/A' }}
                                                            </p>
                                                            
                                                            <p v-if="group.blueprint?.expansion" class="text-sm text-gray-500 dark:text-gray-500">
                                                                {{ group.blueprint.expansion.name }} ({{ group.blueprint.expansion.code }})
                                                            </p>
                                                        </div>
                                                    </div>

                                                    <!-- Right Column - Pricing Info -->
                                                    <div class="flex-shrink-0 flex space-x-3">
                                                        <!-- Single Card Values -->
                                                        <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded-lg border border-gray-200 dark:border-gray-600 w-36">
                                                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2 text-center">Per Card</p>
                                                            <div class="text-center space-y-1">
                                                                <p class="text-sm font-bold text-gray-900 dark:text-gray-100">
                                                                    {{ formatCurrency(group.singleEstimatedValue) }}
                                                                </p>
                                                                <p class="text-xs text-gray-600 dark:text-gray-400">Est. Value</p>
                                                                <div class="border-t border-gray-200 dark:border-gray-600 pt-1 mt-1">
                                                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                                                        {{ formatCurrency(group.singlePurchasePrice) }}
                                                                    </p>
                                                                    <p class="text-xs text-gray-500 dark:text-gray-500">Purchase</p>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Group Total Values -->
                                                        <div class="bg-gradient-to-br from-indigo-50 to-blue-50 dark:from-indigo-900/30 dark:to-blue-900/30 p-3 rounded-lg border border-indigo-200 dark:border-indigo-700 w-36">
                                                            <p class="text-xs font-medium text-indigo-600 dark:text-indigo-400 mb-2 text-center">Total (×{{ group.totalQuantity }})</p>
                                                            <div class="text-center space-y-1">
                                                                <p class="text-sm font-bold text-indigo-900 dark:text-indigo-100">
                                                                    {{ formatCurrency(group.estimatedValueCents ? group.estimatedValueCents / 100 : undefined) }}
                                                                </p>
                                                                <p class="text-xs text-indigo-700 dark:text-indigo-300">Est. Value</p>
                                                                <div class="border-t border-indigo-200 dark:border-indigo-600 pt-1 mt-1">
                                                                    <p class="text-sm font-bold text-indigo-800 dark:text-indigo-200">
                                                                        {{ formatCurrency(group.purchasePriceCents ? group.purchasePriceCents / 100 : undefined) }}
                                                                    </p>
                                                                    <p class="text-xs text-indigo-600 dark:text-indigo-400">Purchase</p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="text-gray-500 dark:text-gray-300">No cards in this portfolio yet.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <AddCardModal 
            v-if="showAddCardModal" 
            @close="closeAddCardModal" 
            :portfolio-id="portfolio.id" 
            :portfolio-name="portfolio.name"
            @card-added="handleCardAddedToCurrentPortfolio"
        />
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AddCardModal from '@/Components/AddCardModal.vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { usePortfolioStore, Portfolio as PortfolioInterface, UserCard as UserCardInterface, Blueprint as BlueprintInterface } from '@/stores/portfolioStore';

// Interfaccia per UserCard come viene passata dal controller, ora allineata con lo store
interface UserCardData extends UserCardInterface {
    // pivot e blueprint sono già definiti in UserCardInterface, ma possiamo specificare ulteriormente se necessario
    // Questa interfaccia potrebbe non essere più strettamente necessaria se UserCardInterface dallo store è sufficiente
}

// Interfaccia per Portfolio come viene passata dal controller, ora allineata con lo store
interface PortfolioPageData extends PortfolioInterface {
    user_cards?: UserCardData[]; // user_cards ora usa UserCardData, che estende la UserCardInterface aggiornata
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
const showAddCardModal = ref(false);

const openAddCardModal = () => {
    showAddCardModal.value = true;
};

const closeAddCardModal = () => {
    showAddCardModal.value = false;
};

const handleCardAddedToCurrentPortfolio = () => {
    // Quando una carta è aggiunta al portfolio corrente, la prop `portfolio` non si aggiorna automaticamente.
    // Dobbiamo ricaricare i dati per questa pagina.
    router.reload({ only: ['portfolio'] }); 
    // In alternativa, potremmo aggiornare manualmente lo store e sperare che la prop si aggiorni,
    // ma router.reload() è più diretto per le props da Inertia.
};

const formatCurrency = (value: number | null | undefined, currency = 'EUR') => {
    if (value === null || typeof value === 'undefined') return 'N/A';
    return new Intl.NumberFormat('it-IT', { style: 'currency', currency: currency }).format(value);
};

const totalEstimatedValue = computed(() => {
    return props.portfolio.user_cards?.reduce((sum, card) => {
        const value = card.estimated_value_cents ? card.estimated_value_cents / 100 : 0;
        return sum + (value * (card.pivot?.quantity || 1));
    }, 0) || 0;
});

const totalPurchaseCost = computed(() => {
    return props.portfolio.user_cards?.reduce((sum, card) => {
        const cost = card.purchase_price_cents ? card.purchase_price_cents / 100 : 0;
        return sum + (cost * (card.pivot?.quantity || 1));
    }, 0) || 0;
});

const groupedCards = computed(() => {
    const cards = props.portfolio.user_cards || [];
    const grouped: Record<string, CardGroup> = {};

    cards.forEach(card => {
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
                singlePurchasePrice: 0
            };
        }
        grouped[groupKey].totalQuantity += card.pivot.quantity;
        grouped[groupKey].estimatedValueCents += card.estimated_value_cents || 0;
        grouped[groupKey].purchasePriceCents += card.purchase_price_cents || 0;
        grouped[groupKey].singleEstimatedValue = card.estimated_value_cents ? card.estimated_value_cents / 100 : 0;
        grouped[groupKey].singlePurchasePrice = card.purchase_price_cents ? card.purchase_price_cents / 100 : 0;
    });

    return Object.values(grouped);
});

</script> 