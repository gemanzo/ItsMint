<template>
  <teleport to="body">
    <div
      v-show="show"
      class="fixed inset-0 z-50 overflow-y-auto"
      aria-labelledby="modal-title"
      role="dialog"
      aria-modal="true"
    >
      <div
        class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0"
      >
        <!-- Background overlay -->
        <div
          v-show="show"
          class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
          aria-hidden="true"
          @click="closeModal"
        />

        <!-- Modal panel -->
        <div
          class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full dark:bg-gray-800"
        >
          <!-- Header -->
          <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 dark:bg-gray-800">
            <div class="sm:flex sm:items-start">
              <div
                class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-indigo-100 sm:mx-0 sm:h-10 sm:w-10 dark:bg-indigo-900"
              >
                <CameraIcon
                  class="h-6 w-6 text-indigo-600 dark:text-indigo-400"
                />
              </div>
              <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                <h3
                  id="modal-title"
                  class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100"
                >
                  Scan Card
                </h3>
                <div class="mt-2">
                  <p class="text-sm text-gray-500 dark:text-gray-400">
                    Take a photo or upload an image to automatically recognize
                    cards
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Content -->
          <div class="px-4 pb-4 sm:p-6 sm:pb-4">
            <!-- Scan method selection -->
            <div class="mb-6">
              <div class="flex space-x-4">
                <button
                  :class="[
                    'flex-1 py-2 px-4 rounded-md text-sm font-medium transition-colors',
                    scanMethod === 'camera'
                      ? 'bg-indigo-600 text-white'
                      : 'bg-gray-200 text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600',
                  ]"
                  @click="scanMethod = 'camera'"
                >
                  <CameraIcon class="h-4 w-4 inline mr-2" />
                  Camera
                </button>
                <button
                  :class="[
                    'flex-1 py-2 px-4 rounded-md text-sm font-medium transition-colors',
                    scanMethod === 'upload'
                      ? 'bg-indigo-600 text-white'
                      : 'bg-gray-200 text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600',
                  ]"
                  @click="scanMethod = 'upload'"
                >
                  <PhotoIcon class="h-4 w-4 inline mr-2" />
                  Upload
                </button>
              </div>
            </div>

            <!-- Camera interface -->
            <div v-if="scanMethod === 'camera'" class="space-y-4">
              <div v-if="!cameraActive && !capturedImage" class="text-center">
                <button
                  :disabled="isProcessing"
                  class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                  @click="startCamera"
                >
                  <CameraIcon class="h-4 w-4 mr-2" />
                  Start Camera
                </button>
              </div>

              <div v-if="cameraActive" class="space-y-4">
                <div class="relative">
                  <video
                    ref="videoElement"
                    autoplay
                    playsinline
                    class="w-full rounded-lg shadow-md"
                  />
                  <canvas ref="canvasElement" class="hidden" />
                </div>
                <div class="flex justify-center space-x-4">
                  <button
                    :disabled="isProcessing"
                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50"
                    @click="capturePhoto"
                  >
                    📷 Capture
                  </button>
                  <button
                    class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600"
                    @click="stopCamera"
                  >
                    Stop Camera
                  </button>
                </div>
              </div>

              <div v-if="capturedImage" class="space-y-4">
                <div class="text-center">
                  <img
                    :src="capturedImage"
                    alt="Captured card"
                    class="max-w-full h-auto rounded-lg shadow-md mx-auto"
                  />
                </div>
                <div class="flex justify-center space-x-4">
                  <button
                    :disabled="isProcessing"
                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                    @click="processCapturedImage"
                  >
                    <span v-if="isProcessing">Processing...</span>
                    <span v-else>Scan Card</span>
                  </button>
                  <button
                    class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600"
                    @click="retakePhoto"
                  >
                    Retake
                  </button>
                </div>
              </div>
            </div>

            <!-- Upload interface -->
            <div v-if="scanMethod === 'upload'" class="space-y-4">
              <div
                class="flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md dark:border-gray-600"
              >
                <div class="space-y-1 text-center">
                  <PhotoIcon class="mx-auto h-12 w-12 text-gray-400" />
                  <div class="flex text-sm text-gray-600 dark:text-gray-400">
                    <label
                      for="file-upload"
                      class="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500 dark:bg-gray-800"
                    >
                      <span>Upload a file</span>
                      <input
                        id="file-upload"
                        name="file-upload"
                        type="file"
                        class="sr-only"
                        accept="image/*"
                        @change="handleFileUpload"
                      />
                    </label>
                    <p class="pl-1">or drag and drop</p>
                  </div>
                  <p class="text-xs text-gray-500 dark:text-gray-400">
                    PNG, JPG, JPEG up to 10MB
                  </p>
                </div>
              </div>

              <div v-if="selectedFile" class="space-y-4">
                <div class="text-center">
                  <img
                    v-if="filePreview"
                    :src="filePreview"
                    alt="Selected card"
                    class="max-w-full h-auto rounded-lg shadow-md mx-auto"
                  />
                </div>
                <div class="flex justify-center">
                  <button
                    :disabled="isProcessing"
                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                    @click="processUploadedFile"
                  >
                    <span v-if="isProcessing">Processing...</span>
                    <span v-else>Scan Card</span>
                  </button>
                </div>
              </div>
            </div>

            <!-- Processing status -->
            <div v-if="isProcessing" class="mt-4">
              <div
                class="bg-blue-50 border border-blue-200 rounded-md p-4 dark:bg-blue-900 dark:border-blue-700"
              >
                <div class="flex">
                  <div class="flex-shrink-0">
                    <svg
                      class="animate-spin h-5 w-5 text-blue-400"
                      xmlns="http://www.w3.org/2000/svg"
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
                  </div>
                  <div class="ml-3">
                    <p class="text-sm text-blue-700 dark:text-blue-300">
                      Processing image with OCR... This may take a few seconds.
                    </p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Error display -->
            <div v-if="error" class="mt-4">
              <div
                class="bg-red-50 border border-red-200 rounded-md p-4 dark:bg-red-900 dark:border-red-700"
              >
                <div class="flex">
                  <div class="flex-shrink-0">
                    <ExclamationTriangleIcon class="h-5 w-5 text-red-400" />
                  </div>
                  <div class="ml-3">
                    <p class="text-sm text-red-700 dark:text-red-300">
                      {{ error }}
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div
            class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse dark:bg-gray-700"
          >
            <button
              type="button"
              class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm dark:bg-gray-600 dark:text-gray-300 dark:border-gray-500 dark:hover:bg-gray-500"
              @click="closeModal"
            >
              Close
            </button>
          </div>
        </div>
      </div>
    </div>
  </teleport>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import {
  CameraIcon,
  PhotoIcon,
  ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline';
import axios from 'axios';

interface Props {
  show: boolean;
}

interface Emits {
  (e: 'close'): void;
  (e: 'scan-complete', scan: any): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

// Reactive variables
const scanMethod = ref<'camera' | 'upload'>('camera');
const cameraActive = ref(false);
const capturedImage = ref<string | null>(null);
const selectedFile = ref<File | null>(null);
const filePreview = ref<string | null>(null);
const isProcessing = ref(false);
const error = ref<string | null>(null);

// DOM refs
const videoElement = ref<HTMLVideoElement | null>(null);
const canvasElement = ref<HTMLCanvasElement | null>(null);

// Stream reference
let mediaStream: MediaStream | null = null;

// Methods
const closeModal = () => {
  stopCamera();
  resetState();
  emit('close');
};

const resetState = () => {
  capturedImage.value = null;
  selectedFile.value = null;
  filePreview.value = null;
  isProcessing.value = false;
  error.value = null;
};

const startCamera = async () => {
  try {
    error.value = null;
    mediaStream = await navigator.mediaDevices.getUserMedia({
      video: {
        facingMode: 'environment', // Use back camera on mobile
        width: { ideal: 1280 },
        height: { ideal: 720 },
      },
    });

    if (videoElement.value) {
      videoElement.value.srcObject = mediaStream;
      cameraActive.value = true;
    }
  } catch (err) {
    error.value = 'Unable to access camera. Please check permissions.';
    console.error('Camera error:', err);
  }
};

const stopCamera = () => {
  if (mediaStream) {
    mediaStream.getTracks().forEach((track) => track.stop());
    mediaStream = null;
  }
  cameraActive.value = false;
};

const capturePhoto = () => {
  if (!videoElement.value || !canvasElement.value) return;

  const video = videoElement.value;
  const canvas = canvasElement.value;
  const context = canvas.getContext('2d');

  if (!context) return;

  // Set canvas size to video size
  canvas.width = video.videoWidth;
  canvas.height = video.videoHeight;

  // Draw video frame to canvas
  context.drawImage(video, 0, 0, canvas.width, canvas.height);

  // Convert to blob and create URL
  canvas.toBlob(
    (blob) => {
      if (blob) {
        capturedImage.value = URL.createObjectURL(blob);
        stopCamera();
      }
    },
    'image/jpeg',
    0.9
  );
};

const retakePhoto = () => {
  capturedImage.value = null;
  startCamera();
};

const processCapturedImage = async () => {
  if (!capturedImage.value || !canvasElement.value) return;

  isProcessing.value = true;
  error.value = null;

  try {
    // Convert canvas to blob
    const response = await new Promise<Blob>((resolve) => {
      canvasElement.value!.toBlob(
        (blob) => {
          resolve(blob!);
        },
        'image/jpeg',
        0.9
      );
    });

    // Create FormData and upload
    const formData = new FormData();
    formData.append('image', response, 'captured_card.jpg');
    formData.append('source', 'camera');

    const result = await axios.post('/card-scans/upload', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });

    emit('scan-complete', result.data.scan);
    closeModal();
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to process image';
    console.error('Upload error:', err);
  } finally {
    isProcessing.value = false;
  }
};

const handleFileUpload = (event: Event) => {
  const target = event.target as HTMLInputElement;
  const file = target.files?.[0];

  if (file) {
    selectedFile.value = file;

    // Create preview
    const reader = new FileReader();
    reader.onload = (e) => {
      filePreview.value = e.target?.result as string;
    };
    reader.readAsDataURL(file);
  }
};

const processUploadedFile = async () => {
  if (!selectedFile.value) return;

  isProcessing.value = true;
  error.value = null;

  try {
    const formData = new FormData();
    formData.append('image', selectedFile.value);
    formData.append('source', 'upload');

    const result = await axios.post('/card-scans/upload', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });

    emit('scan-complete', result.data.scan);
    closeModal();
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to process image';
    console.error('Upload error:', err);
  } finally {
    isProcessing.value = false;
  }
};

// Cleanup on unmount
onUnmounted(() => {
  stopCamera();
});
</script>
