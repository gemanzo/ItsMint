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
                            <h2 class="text-xl font-semibold mb-3">Cards in Portfolio</h2>
                            <div v-if="portfolio.user_cards && portfolio.user_cards.length > 0" class="space-y-3">
                                <div v-for="item in portfolio.user_cards" :key="item.id" class="bg-gray-50 dark:bg-gray-700 p-4 rounded-md shadow-sm flex justify-between items-center">
                                    <div>
                                        <p class="font-medium text-lg">{{ item.blueprint?.name }}</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-300">Condition: {{ item.condition }} | Language: {{ item.language }} | Qty: {{ item.pivot.quantity }}</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-300">Acquired: {{ item.acquired_at ? new Date(item.acquired_at).toLocaleDateString() : 'N/A' }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-md font-semibold">Est. Value: {{ formatCurrency(item.estimated_value_cents ? item.estimated_value_cents / 100 : undefined) }}</p>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Purchase: {{ formatCurrency(item.purchase_price_cents ? item.purchase_price_cents / 100 : undefined) }}</p>
                                    </div>
                                    <!-- TODO: Add actions per card (edit, delete) -->
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

</script> 