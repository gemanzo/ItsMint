<template>
    <AuthenticatedLayout>
        <Head title="My Portfolios" />

        <!-- Enhanced Header with Stats -->
        <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">My Portfolios</h1>
                        <p class="text-gray-600 dark:text-gray-400 mt-1">
                            {{ portfolios.length }} {{ portfolios.length === 1 ? 'portfolio' : 'portfolios' }} • {{ totalCards }} total cards • {{ formatCurrency(totalValue) }} total value
                        </p>
                    </div>
                    <div class="mt-4 sm:mt-0 flex space-x-3">
                        <button 
                            @click="viewMode = viewMode === 'grid' ? 'list' : 'grid'"
                            class="px-3 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg transition-colors flex items-center space-x-2"
                        >
                            <svg v-if="viewMode === 'grid'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
                            </svg>
                            <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                            </svg>
                            <span>{{ viewMode === 'grid' ? 'List' : 'Grid' }}</span>
                        </button>
                    <button 
                        @click="showCreatePortfolioModal = true"
                            class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg shadow-lg hover:shadow-xl transition-all duration-300 flex items-center space-x-2"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            <span>Create New Portfolio</span>
                    </button>
                    </div>
                </div>

                <!-- Quick Stats Bar -->
                <div v-if="portfolios.length > 0" class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 border border-blue-200 dark:border-blue-800">
                        <p class="text-blue-600 dark:text-blue-400 text-sm font-medium">Total Portfolios</p>
                        <p class="text-2xl font-bold text-blue-900 dark:text-blue-100">{{ portfolios.length }}</p>
                    </div>
                    <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4 border border-green-200 dark:border-green-800">
                        <p class="text-green-600 dark:text-green-400 text-sm font-medium">Total Cards</p>
                        <p class="text-2xl font-bold text-green-900 dark:text-green-100">{{ totalCards }}</p>
                    </div>
                    <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4 border border-purple-200 dark:border-purple-800">
                        <p class="text-purple-600 dark:text-purple-400 text-sm font-medium">Avg per Portfolio</p>
                        <p class="text-2xl font-bold text-purple-900 dark:text-purple-100">{{ Math.round(totalCards / portfolios.length) }}</p>
                    </div>
                    <div class="bg-orange-50 dark:bg-orange-900/20 rounded-lg p-4 border border-orange-200 dark:border-orange-800">
                        <p class="text-orange-600 dark:text-orange-400 text-sm font-medium">Total Value</p>
                        <p class="text-2xl font-bold text-orange-900 dark:text-orange-100">{{ formatCurrency(totalValue) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="min-h-screen bg-gray-50 dark:bg-gray-900 py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <!-- Search and Filters -->
                <div v-if="portfolios.length > 0" class="mb-8">
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-4 sm:space-y-0 sm:space-x-4">
                            <div class="relative flex-1 max-w-md">
                                <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                                <input 
                                    v-model="searchQuery"
                                    type="text" 
                                    placeholder="Search portfolios..." 
                                    class="w-full px-4 py-2 pl-10 pr-4 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500 focus:border-transparent"
                                />
                            </div>
                            <div class="flex items-center space-x-4">
                                <select v-model="sortBy" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500">
                                    <option value="name">Sort by Name</option>
                                    <option value="cards">Sort by Card Count</option>
                                    <option value="value">Sort by Value</option>
                                    <option value="created">Sort by Date Created</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Portfolios Grid/List -->
                <div v-if="filteredPortfolios.length > 0">
                    <!-- Grid View -->
                    <div v-if="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div v-for="portfolio in filteredPortfolios" :key="portfolio.id" 
                             class="group bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700 hover:shadow-lg hover:-translate-y-1 transition-all duration-300 cursor-pointer"
                             @click="$inertia.visit(route('portfolios.show', portfolio.id))">
                            
                            <!-- Portfolio Header -->
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex-1">
                                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                                        {{ portfolio.name }}
                                    </h3>
                                    <p v-if="portfolio.description" class="text-sm text-gray-600 dark:text-gray-400 mt-1 line-clamp-2">
                                        {{ portfolio.description }}
                                    </p>
                                </div>
                                <div class="h-10 w-10 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center ml-4">
                                    <svg class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                    </svg>
                                </div>
                            </div>

                            <!-- Portfolio Stats -->
                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Cards</p>
                                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ portfolio.user_cards_count || 0 }}</p>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Est. Value</p>
                                    <p class="text-lg font-bold text-gray-900 dark:text-white">{{ formatCurrency(calculatePortfolioValue(portfolio)) }}</p>
                                </div>
                            </div>

                            <!-- Portfolio Actions -->
                            <div class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-gray-700">
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    Created {{ formatDate(portfolio.created_at) }}
                                </span>
                                <div class="flex items-center space-x-2">
                                    <button @click.stop="editPortfolio(portfolio)" class="p-1 text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </button>
                                    <button @click.stop="deletePortfolio(portfolio)" class="p-1 text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition-colors">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- List View -->
                    <div v-else class="space-y-4">
                        <div v-for="portfolio in filteredPortfolios" :key="portfolio.id" 
                             class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border border-gray-200 dark:border-gray-700 hover:shadow-lg transition-all duration-300 cursor-pointer"
                             @click="$inertia.visit(route('portfolios.show', portfolio.id))">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-6 flex-1">
                                    <div class="h-12 w-12 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center">
                                        <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                        </svg>
                                    </div>
                                    <div class="flex-1">
                                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ portfolio.name }}</h3>
                                        <p v-if="portfolio.description" class="text-sm text-gray-600 dark:text-gray-400">{{ portfolio.description }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-6 text-sm text-gray-500 dark:text-gray-400">
                                    <div class="text-center">
                                        <p class="font-semibold text-gray-900 dark:text-white">{{ portfolio.user_cards_count || 0 }}</p>
                                        <p class="text-xs">Cards</p>
                                    </div>
                                    <div class="text-center">
                                        <p class="font-semibold text-gray-900 dark:text-white">{{ formatCurrency(calculatePortfolioValue(portfolio)) }}</p>
                                        <p class="text-xs">Value</p>
                                    </div>
                                    <div class="text-center">
                                        <p class="font-semibold text-gray-900 dark:text-white">{{ formatDate(portfolio.created_at) }}</p>
                                        <p class="text-xs">Created</p>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <button @click.stop="editPortfolio(portfolio)" class="p-2 text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </button>
                                        <button @click.stop="deletePortfolio(portfolio)" class="p-2 text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition-colors">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else-if="portfolios.length === 0" class="text-center py-16">
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-12 shadow-sm border border-gray-200 dark:border-gray-700">
                        <svg class="h-20 w-20 text-gray-400 dark:text-gray-600 mx-auto mb-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">No Portfolios Yet</h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-8 max-w-md mx-auto">
                            Start building your One Piece TCG collection by creating your first portfolio. Organize your cards, track values, and monitor your collection's performance.
                        </p>
                        <button 
                            @click="showCreatePortfolioModal = true"
                            class="px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg shadow-lg hover:shadow-xl transition-all duration-300"
                        >
                            🎯 Create Your First Portfolio
                        </button>
                    </div>
                </div>

                <!-- No Search Results -->
                <div v-else-if="searchQuery && filteredPortfolios.length === 0" class="text-center py-16">
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-12 shadow-sm border border-gray-200 dark:border-gray-700">
                        <svg class="h-16 w-16 text-gray-400 dark:text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">No portfolios found</h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-4">
                            Try adjusting your search terms or create a new portfolio.
                        </p>
                        <button 
                            @click="searchQuery = ''"
                            class="px-4 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 rounded-lg transition-colors"
                        >
                            Clear Search
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <CreatePortfolioModal v-if="showCreatePortfolioModal" @close="showCreatePortfolioModal = false" @portfolio-created="handlePortfolioCreated" />
        
        <ConfirmationModal 
            :show="showConfirmDeleteModal"
            title="Delete Portfolio"
            :message="`Are you sure you want to delete '${portfolioToDelete?.name}'? This action cannot be undone.`"
            @close="showConfirmDeleteModal = false"
            @confirm="confirmDeletePortfolio"
        />
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import CreatePortfolioModal from '@/Components/CreatePortfolioModal.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import { usePortfolioStore } from '@/stores/portfolioStore';

interface PortfolioData {
    id: number;
    name: string;
    description?: string;
    user_cards_count: number;
    created_at: string;
    // Altri campi se inviati dal controller
}

const props = defineProps<{
    portfolios: PortfolioData[];
}>();

const portfolioStore = usePortfolioStore();

// Reactive variables
const showCreatePortfolioModal = ref(false);
const viewMode = ref<'grid' | 'list'>('grid');
const searchQuery = ref('');
const sortBy = ref('name');
const showConfirmDeleteModal = ref(false);
const portfolioToDelete = ref<PortfolioData | null>(null);

// Computed properties
const totalCards = computed(() => {
    return props.portfolios.reduce((sum, portfolio) => sum + (portfolio.user_cards_count || 0), 0);
});

const totalValue = computed(() => {
    // Per ora ritorniamo un valore mock, in futuro si potrebbe calcolare dal valore stimato
    return props.portfolios.reduce((sum, portfolio) => sum + ((portfolio.user_cards_count || 0) * 5.5), 0);
});

const filteredPortfolios = computed(() => {
    let filtered = [...props.portfolios];
    
    // Search filter
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter(portfolio => 
            portfolio.name.toLowerCase().includes(query) ||
            (portfolio.description && portfolio.description.toLowerCase().includes(query))
        );
    }
    
    // Sort
    filtered.sort((a, b) => {
        switch (sortBy.value) {
            case 'name':
                return a.name.localeCompare(b.name);
            case 'cards':
                return (b.user_cards_count || 0) - (a.user_cards_count || 0);
            case 'value':
                return calculatePortfolioValue(b) - calculatePortfolioValue(a);
            case 'created':
                return new Date(b.created_at).getTime() - new Date(a.created_at).getTime();
            default:
                return 0;
        }
    });
    
    return filtered;
});

// Methods
const formatCurrency = (value: number | null | undefined, currency = 'EUR') => {
    if (value === null || typeof value === 'undefined') return 'N/A';
    return new Intl.NumberFormat('it-IT', { style: 'currency', currency: currency }).format(value);
};

const formatDate = (dateString: string) => {
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString('it-IT', { 
            day: '2-digit', 
            month: '2-digit', 
            year: 'numeric' 
        });
    } catch {
        return 'N/A';
    }
};

const calculatePortfolioValue = (portfolio: PortfolioData) => {
    // Mock calculation - in futuro questo potrebbe essere basato sui valori reali delle carte
    return (portfolio.user_cards_count || 0) * 5.5; // Media di €5.50 per carta
};

const editPortfolio = (portfolio: PortfolioData) => {
    // TODO: Implementare edit portfolio modal
    console.log('Edit portfolio:', portfolio.id);
    // router.visit(route('portfolios.edit', portfolio.id));
};

const deletePortfolio = (portfolio: PortfolioData) => {
    portfolioToDelete.value = portfolio;
    showConfirmDeleteModal.value = true;
};

const confirmDeletePortfolio = async () => {
    if (!portfolioToDelete.value) return;
    try {
        await portfolioStore.deletePortfolio(portfolioToDelete.value.id);
        router.reload({ only: ['portfolios'] });
    } catch (error) {
        console.error('Error deleting portfolio:', error);
        alert('Failed to delete portfolio. Please try again.');
    } finally {
        showConfirmDeleteModal.value = false;
        portfolioToDelete.value = null;
    }
};

const handlePortfolioCreated = () => {
    console.log('Portfolio created, refreshing list');
    portfolioStore.fetchPortfolios();
    router.reload({ only: ['portfolios'] });
};

</script> 

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Smooth hover animations */
.group:hover .group-hover\:text-blue-600 {
    transition: color 0.3s ease;
}

/* Progress bar animation */
.bg-blue-500 {
    transition: width 0.5s ease-in-out;
}
</style> 