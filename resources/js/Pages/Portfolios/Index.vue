<template>
    <AuthenticatedLayout>
        <Head title="My Portfolios" />

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">My Portfolios</h1>
                    <button 
                        @click="showCreatePortfolioModal = true"
                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg shadow">
                        + Create New Portfolio
                    </button>
                </div>

                <div v-if="portfolios.length > 0" class="space-y-4">
                    <div v-for="portfolio in portfolios" :key="portfolio.id" 
                         class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 hover:shadow-md transition-shadow duration-200">
                        <Link :href="route('portfolios.show', portfolio.id)" class="block">
                            <div class="flex justify-between items-center">
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ portfolio.name }}</h2>
                                <span class="text-sm text-gray-500 dark:text-gray-400">{{ portfolio.user_cards_count }} card(s)</span>
                            </div>
                            <p v-if="portfolio.description" class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ portfolio.description }}</p>
                            <!-- Potremmo aggiungere un piccolo riepilogo del valore qui se vogliamo -->
                        </Link>
                    </div>
                </div>
                <div v-else class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <p class="text-gray-900 dark:text-gray-100">You don't have any portfolios yet. Create one to get started!</p>
                </div>
            </div>
        </div>

        <CreatePortfolioModal v-if="showCreatePortfolioModal" @close="showCreatePortfolioModal = false" @portfolio-created="handlePortfolioCreated" />
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import CreatePortfolioModal from '@/Components/CreatePortfolioModal.vue';
import { usePortfolioStore } from '@/stores/portfolioStore'; // Per aggiornare la lista se necessario

interface PortfolioData {
    id: number;
    name: string;
    description?: string;
    user_cards_count: number;
    // Altri campi se inviati dal controller
}

const props = defineProps<{
    portfolios: PortfolioData[];
}>();

const portfolioStore = usePortfolioStore(); // Anche se i dati arrivano come props, lo store può essere usato per azioni
const showCreatePortfolioModal = ref(false);

const handlePortfolioCreated = () => {
    // La prop 'portfolios' non si aggiornerà automaticamente qui.
    // La cosa migliore è ricaricare la pagina o fare in modo che lo store gestisca i dati dei portfolio
    // e questa pagina osservi lo store, oppure forzare un reload della pagina Inertia.
    // Per ora, un semplice log. Se lo store viene usato come single source of truth per i portfolio,
    // la lista si aggiornerebbe automaticamente.
    console.log('Portfolio created, ideally refresh list or use store state');
    // Per un refresh completo:
    // import { router } from '@inertiajs/vue3'
    // router.reload({ only: ['portfolios'] });
    // Oppure, se lo store è la fonte principale, basta che lo store si aggiorni
    // e questa vista (se usa lo store per `portfolios`) rifletterà i cambiamenti.
    // Dato che usiamo props, lo store qui non aggiorna direttamente la vista.
    // L'opzione più pulita è spesso usare lo store come SSoT per dati dinamici.
    // In questo caso, il controller passa i dati iniziali, ma la creazione via modale aggiorna lo store.
    // Si potrebbe forzare un fetchPortfolios() nello store e poi un router.reload() se la pagina non è basata sullo store.
    portfolioStore.fetchPortfolios(); // Dice allo store di aggiornarsi, ma non aggiorna le props.
};

</script> 