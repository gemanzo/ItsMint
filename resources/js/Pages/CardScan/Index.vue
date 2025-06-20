<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Card Scanner
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <!-- Header Section -->
                        <div class="text-center mb-8">
                            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-indigo-100 dark:bg-indigo-900 mb-4">
                                <CameraIcon class="h-6 w-6 text-indigo-600 dark:text-indigo-400" />
                            </div>
                            <h3 class="text-lg leading-6 font-medium">Automatic Card Recognition</h3>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                Use your camera or upload an image to automatically identify and add cards to your collection
                            </p>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex flex-col sm:flex-row gap-4 justify-center mb-8">
                            <button 
                                @click="openScanModal"
                                class="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            >
                                <CameraIcon class="h-5 w-5 mr-2" />
                                Scan New Card
                            </button>
                            
                            <button 
                                @click="loadScanHistory"
                                class="inline-flex items-center justify-center px-6 py-3 border border-gray-300 text-base font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600"
                            >
                                <ClockIcon class="h-5 w-5 mr-2" />
                                View History
                            </button>
                        </div>

                        <!-- Recent Scans -->
                        <div v-if="recentScans.length > 0" class="mt-8">
                            <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Recent Scans</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <div 
                                    v-for="scan in recentScans" 
                                    :key="scan.id"
                                    class="border rounded-lg p-4 hover:shadow-md transition-shadow cursor-pointer dark:border-gray-600"
                                    @click="viewScanDetails(scan)"
                                >
                                    <div class="flex items-start space-x-3">
                                        <!-- Status indicator -->
                                        <div class="flex-shrink-0">
                                            <CheckCircleIcon v-if="scan.status === 'completed'" class="h-5 w-5 text-green-500" />
                                            <ExclamationTriangleIcon v-else-if="scan.status === 'failed'" class="h-5 w-5 text-red-500" />
                                            <ClockIcon v-else class="h-5 w-5 text-yellow-500" />
                                        </div>
                                        
                                        <div class="flex-1 min-w-0">
                                            <!-- Card name if detected -->
                                            <p v-if="scan.blueprint" class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                                                {{ scan.blueprint.name }}
                                            </p>
                                            <p v-else class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                                {{ scan.detected_cards?.length ? `${scan.detected_cards.length} cards detected` : 'No cards detected' }}
                                            </p>
                                            
                                            <!-- Timestamp -->
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ formatDate(scan.created_at) }}
                                            </p>
                                            
                                            <!-- Confidence score -->
                                            <div v-if="scan.confidence_score" class="mt-1">
                                                <span 
                                                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                                    :class="getConfidenceClass(scan.confidence_score)"
                                                >
                                                    {{ scan.confidence_score }}% confidence
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- No scans message -->
                        <div v-else-if="!isLoading" class="text-center py-8">
                            <PhotoIcon class="h-12 w-12 text-gray-400 mx-auto mb-4" />
                            <p class="text-gray-500 dark:text-gray-400">No scans yet. Start by scanning your first card!</p>
                        </div>

                        <!-- Loading state -->
                        <div v-if="isLoading" class="text-center py-8">
                            <div class="animate-spin h-8 w-8 border-4 border-indigo-600 border-t-transparent rounded-full mx-auto"></div>
                            <p class="text-gray-500 dark:text-gray-400 mt-2">Loading scans...</p>
                        </div>

                        <!-- Features Info -->
                        <div class="mt-12 border-t pt-8 dark:border-gray-600">
                            <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">How it works</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="text-center">
                                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 dark:bg-blue-900 mb-3">
                                        <CameraIcon class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                                    </div>
                                    <h5 class="text-sm font-medium text-gray-900 dark:text-gray-100">1. Capture or Upload</h5>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Take a photo or upload an image of your card</p>
                                </div>
                                
                                <div class="text-center">
                                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100 dark:bg-green-900 mb-3">
                                        <EyeIcon class="h-6 w-6 text-green-600 dark:text-green-400" />
                                    </div>
                                    <h5 class="text-sm font-medium text-gray-900 dark:text-gray-100">2. OCR Recognition</h5>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Our AI reads and identifies the card details</p>
                                </div>
                                
                                <div class="text-center">
                                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-purple-100 dark:bg-purple-900 mb-3">
                                        <PlusIcon class="h-6 w-6 text-purple-600 dark:text-purple-400" />
                                    </div>
                                    <h5 class="text-sm font-medium text-gray-900 dark:text-gray-100">3. Add to Collection</h5>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Review and add the card to your portfolio</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Scan Modal -->
        <CardScanModal 
            :show="showScanModal"
            @close="closeScanModal"
            @scan-complete="handleScanComplete"
        />

        <!-- Scan Results Modal -->
        <ScanResultsModal 
            :show="showScanResults"
            :scan="currentScan"
            @close="closeScanResults"
            @card-created="handleCardCreated"
        />
    </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import CardScanModal from '@/Components/CardScanModal.vue'
import ScanResultsModal from '@/Components/ScanResultsModal.vue'
import { 
    CameraIcon, 
    ClockIcon, 
    CheckCircleIcon, 
    ExclamationTriangleIcon,
    PhotoIcon,
    EyeIcon,
    PlusIcon
} from '@heroicons/vue/24/outline'
import axios from 'axios'

interface CardScan {
    id: number
    status: string
    blueprint?: any
    detected_cards?: any[]
    created_at: string
    confidence_score?: number
}

// Reactive state
const showScanModal = ref(false)
const showScanResults = ref(false)
const currentScan = ref<CardScan | null>(null)
const recentScans = ref<CardScan[]>([])
const isLoading = ref(false)

// Methods
const openScanModal = () => {
    showScanModal.value = true
}

const closeScanModal = () => {
    showScanModal.value = false
}

const handleScanComplete = (scan: any) => {
    currentScan.value = scan
    showScanModal.value = false
    showScanResults.value = true
    
    // Add to recent scans
    recentScans.value.unshift(scan)
    if (recentScans.value.length > 5) {
        recentScans.value.pop()
    }
}

const closeScanResults = () => {
    showScanResults.value = false
    currentScan.value = null
}

const handleCardCreated = () => {
    // Optionally, refresh history or show a success message
    // For now, closing the results modal is enough
    showScanResults.value = false
}

const viewScanDetails = (scan: CardScan) => {
    currentScan.value = scan
    showScanResults.value = true
}

const loadScanHistory = async () => {
    isLoading.value = true
    try {
        const response = await axios.get('/card-scans')
        recentScans.value = response.data.data
    } catch (error) {
        console.error('Failed to load scan history:', error)
        // Optionally, show an error to the user
    } finally {
        isLoading.value = false
    }
}

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString(undefined, {
        year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
    })
}

const getConfidenceClass = (score: number) => {
    if (score > 85) return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'
    if (score > 60) return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300'
    return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'
}

onMounted(() => {
    loadScanHistory()
})
</script> 