<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';

interface Props {
  inviteCode?: string;
  requiresInvite?: boolean;
}

const props = defineProps<Props>();

const form = useForm({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  inviteCode: props.inviteCode || '',
  terms_accepted: false,
});

// Set invite code from URL on mount
onMounted(() => {
  if (props.inviteCode) {
    form.inviteCode = props.inviteCode;
  }
});

const showPassword = ref(false);
const showPasswordConfirmation = ref(false);

// Password strength indicator
const passwordStrength = computed(() => {
  const password = form.password;
  if (!password) return { level: 0, text: '', color: 'gray' };

  let score = 0;
  if (password.length >= 8) score++;
  if (/[a-z]/.test(password)) score++;
  if (/[A-Z]/.test(password)) score++;
  if (/[0-9]/.test(password)) score++;
  if (/[^A-Za-z0-9]/.test(password)) score++;

  if (score < 2) return { level: 1, text: 'Weak', color: 'red' };
  if (score < 4) return { level: 2, text: 'Fair', color: 'yellow' };
  if (score < 5) return { level: 3, text: 'Good', color: 'blue' };
  return { level: 4, text: 'Strong', color: 'green' };
});

const submit = () => {
  form.post(route('register'), {
    onFinish: () => {
      form.reset('password', 'password_confirmation');
    },
  });
};

const togglePasswordVisibility = () => {
  showPassword.value = !showPassword.value;
};

const togglePasswordConfirmationVisibility = () => {
  showPasswordConfirmation.value = !showPasswordConfirmation.value;
};
</script>

<template>
  <Head title="Register - ItsMint TCG Collection" />

  <div
    class="min-h-screen bg-gradient-to-br from-red-50 via-orange-50 to-yellow-50 dark:from-gray-900 dark:via-red-900 dark:to-yellow-900 flex flex-col justify-center py-12 sm:px-6 lg:px-8"
  >
    <!-- Background Pattern -->
    <div class="absolute inset-0 opacity-5">
      <div
        class="absolute inset-0"
        style="
          background-image:
            radial-gradient(circle at 25px 25px, #dc2626 2px, transparent 0),
            radial-gradient(circle at 75px 75px, #f59e0b 2px, transparent 0);
          background-size: 100px 100px;
        "
      />
    </div>

    <div class="relative sm:mx-auto sm:w-full sm:max-w-md">
      <!-- Large Logo Section -->
      <div class="text-center mb-8">
        <Link href="/" class="inline-block group">
          <!-- Enhanced Large Logo -->
          <div
            class="flex items-center justify-center space-x-3 mb-4 transform group-hover:scale-105 transition-transform duration-300"
          >
            <!-- Card Stack Icon - Larger -->
            <div class="relative">
              <div
                class="w-16 h-16 bg-gradient-to-r from-red-600 to-orange-600 rounded-xl shadow-lg transform rotate-3 absolute -top-1 -left-1"
              />
              <div
                class="w-16 h-16 bg-gradient-to-r from-orange-600 to-yellow-500 rounded-xl shadow-lg transform -rotate-2 absolute top-0 left-0"
              />
              <div
                class="w-16 h-16 bg-gradient-to-r from-red-500 to-yellow-400 rounded-xl shadow-xl relative z-10 flex items-center justify-center"
              >
                <svg
                  class="w-8 h-8 text-white"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M19 11H5m14-7H3a2 2 0 00-2 2v12a2 2 0 002 2h16a2 2 0 002-2V6a2 2 0 00-2-2z"
                  />
                </svg>
              </div>
            </div>
            <!-- Text Logo -->
            <div>
              <h1
                class="text-4xl font-bold bg-gradient-to-r from-red-600 via-orange-600 to-yellow-600 bg-clip-text text-transparent"
              >
                ItsMint
              </h1>
              <p
                class="text-sm text-gray-600 dark:text-gray-400 font-medium tracking-wider"
              >
                One Piece TCG Collection
              </p>
            </div>
          </div>
        </Link>

        <!-- Welcome Message -->
        <div class="space-y-2">
          <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
            {{ requiresInvite ? 'Join the Alpha!' : 'Join ItsMint Today!' }}
          </h2>
          <p class="text-gray-600 dark:text-gray-400">
            {{
              requiresInvite
                ? "You've been invited to test our alpha version"
                : 'Start your One Piece TCG collection journey'
            }}
          </p>
          <!-- Alpha Badge -->
          <div
            v-if="requiresInvite"
            class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gradient-to-r from-red-100 to-orange-100 text-red-800 dark:from-red-900 dark:to-orange-900 dark:text-red-200"
          >
            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
              <path
                d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"
              />
            </svg>
            Alpha Tester
          </div>
        </div>
      </div>

      <!-- Register Card -->
      <div
        class="bg-white dark:bg-gray-800 py-8 px-6 shadow-2xl rounded-2xl border border-gray-200 dark:border-gray-700 backdrop-blur-sm bg-opacity-95 dark:bg-opacity-95"
      >
        <form class="space-y-6" @submit.prevent="submit">
          <!-- Invitation Code Field (if required) -->
          <div v-if="requiresInvite">
            <InputLabel
              for="inviteCode"
              value="Invitation Code"
              class="text-sm font-medium text-gray-700 dark:text-gray-300"
            />
            <div class="mt-2 relative">
              <div
                class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"
              >
                <svg
                  class="h-5 w-5 text-gray-400"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M15 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                  />
                </svg>
              </div>
              <TextInput
                id="inviteCode"
                v-model="form.inviteCode"
                type="text"
                class="block w-full pl-10 pr-3 py-3 border border-gray-300 dark:border-gray-600 rounded-xl shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent text-sm bg-white dark:bg-gray-700 uppercase tracking-wider font-mono"
                :required="requiresInvite"
                placeholder="XXXXXXXX"
                maxlength="8"
                style="text-transform: uppercase"
              />
            </div>
            <InputError class="mt-2" :message="form.errors.inviteCode" />
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
              Enter the 8-character invitation code you received
            </p>
          </div>

          <!-- Name Field -->
          <div>
            <InputLabel
              for="name"
              value="Full Name"
              class="text-sm font-medium text-gray-700 dark:text-gray-300"
            />
            <div class="mt-2 relative">
              <div
                class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"
              >
                <svg
                  class="h-5 w-5 text-gray-400"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                  />
                </svg>
              </div>
              <TextInput
                id="name"
                v-model="form.name"
                type="text"
                class="block w-full pl-10 pr-3 py-3 border border-gray-300 dark:border-gray-600 rounded-xl shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent text-sm bg-white dark:bg-gray-700"
                required
                :autofocus="!requiresInvite"
                autocomplete="name"
                placeholder="Enter your full name"
              />
            </div>
            <InputError class="mt-2" :message="form.errors.name" />
          </div>

          <!-- Email Field -->
          <div>
            <InputLabel
              for="email"
              value="Email Address"
              class="text-sm font-medium text-gray-700 dark:text-gray-300"
            />
            <div class="mt-2 relative">
              <div
                class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"
              >
                <svg
                  class="h-5 w-5 text-gray-400"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"
                  />
                </svg>
              </div>
              <TextInput
                id="email"
                v-model="form.email"
                type="email"
                class="block w-full pl-10 pr-3 py-3 border border-gray-300 dark:border-gray-600 rounded-xl shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent text-sm bg-white dark:bg-gray-700"
                required
                autocomplete="username"
                placeholder="Enter your email address"
              />
            </div>
            <InputError class="mt-2" :message="form.errors.email" />
          </div>

          <!-- Password Field -->
          <div>
            <InputLabel
              for="password"
              value="Password"
              class="text-sm font-medium text-gray-700 dark:text-gray-300"
            />
            <div class="mt-2 relative">
              <div
                class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"
              >
                <svg
                  class="h-5 w-5 text-gray-400"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                  />
                </svg>
              </div>
              <TextInput
                id="password"
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                class="block w-full pl-10 pr-12 py-3 border border-gray-300 dark:border-gray-600 rounded-xl shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent text-sm bg-white dark:bg-gray-700"
                required
                autocomplete="new-password"
                placeholder="Create a strong password"
              />
              <button
                type="button"
                class="absolute inset-y-0 right-0 pr-3 flex items-center hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                @click="togglePasswordVisibility"
              >
                <svg
                  v-if="!showPassword"
                  class="h-5 w-5 text-gray-400"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                  />
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                  />
                </svg>
                <svg
                  v-else
                  class="h-5 w-5 text-gray-400"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"
                  />
                </svg>
              </button>
            </div>

            <!-- Password Strength Indicator -->
            <div v-if="form.password" class="mt-2">
              <div class="flex items-center justify-between text-xs mb-1">
                <span class="text-gray-500 dark:text-gray-400"
                  >Password strength:</span
                >
                <span
                  :class="`font-medium text-${passwordStrength.color}-600 dark:text-${passwordStrength.color}-400`"
                >
                  {{ passwordStrength.text }}
                </span>
              </div>
              <div
                class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5"
              >
                <div
                  :class="`h-1.5 rounded-full transition-all duration-300 bg-${passwordStrength.color}-500`"
                  :style="`width: ${(passwordStrength.level / 4) * 100}%`"
                />
              </div>
            </div>

            <InputError class="mt-2" :message="form.errors.password" />
          </div>

          <!-- Confirm Password Field -->
          <div>
            <InputLabel
              for="password_confirmation"
              value="Confirm Password"
              class="text-sm font-medium text-gray-700 dark:text-gray-300"
            />
            <div class="mt-2 relative">
              <div
                class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"
              >
                <svg
                  class="h-5 w-5 text-gray-400"
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
              </div>
              <TextInput
                id="password_confirmation"
                v-model="form.password_confirmation"
                :type="showPasswordConfirmation ? 'text' : 'password'"
                class="block w-full pl-10 pr-12 py-3 border border-gray-300 dark:border-gray-600 rounded-xl shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent text-sm bg-white dark:bg-gray-700"
                required
                autocomplete="new-password"
                placeholder="Confirm your password"
              />
              <button
                type="button"
                class="absolute inset-y-0 right-0 pr-3 flex items-center hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                @click="togglePasswordConfirmationVisibility"
              >
                <svg
                  v-if="!showPasswordConfirmation"
                  class="h-5 w-5 text-gray-400"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                  />
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                  />
                </svg>
                <svg
                  v-else
                  class="h-5 w-5 text-gray-400"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"
                  />
                </svg>
              </button>
            </div>
            <InputError
              class="mt-2"
              :message="form.errors.password_confirmation"
            />
          </div>

          <!-- Terms and Login Link -->
          <div class="flex items-center justify-between text-sm">
            <Link
              :href="route('login')"
              class="font-medium text-red-600 hover:text-orange-600 dark:text-red-400 dark:hover:text-orange-400 transition-colors"
            >
              Already have an account?
            </Link>
          </div>

          <!-- Register Button -->
          <div>
            <PrimaryButton
              type="submit"
              class="w-full flex justify-center py-3 px-4 border border-transparent rounded-xl shadow-lg text-sm font-medium text-white bg-gradient-to-r from-red-600 to-orange-600 hover:from-red-700 hover:to-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50 disabled:cursor-not-allowed transform transition-all duration-200 hover:scale-[1.02] active:scale-[0.98]"
              :class="{ 'opacity-75 cursor-not-allowed': form.processing }"
              :disabled="form.processing"
            >
              <svg
                v-if="form.processing"
                class="animate-spin -ml-1 mr-3 h-5 w-5 text-white"
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
              <span v-if="form.processing">Creating account...</span>
              <span v-else>{{
                requiresInvite ? 'Join Alpha Test' : 'Create Account'
              }}</span>
            </PrimaryButton>
          </div>
        </form>

        <!-- Alpha Notice -->
        <div
          v-if="requiresInvite"
          class="mt-6 p-4 bg-gradient-to-r from-red-50 to-orange-50 dark:from-red-900/20 dark:to-orange-900/20 rounded-xl border border-red-200 dark:border-red-800"
        >
          <div class="flex items-start">
            <svg
              class="flex-shrink-0 w-5 h-5 text-red-600 dark:text-red-400 mt-0.5"
              fill="currentColor"
              viewBox="0 0 20 20"
            >
              <path
                fill-rule="evenodd"
                d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                clip-rule="evenodd"
              />
            </svg>
            <div class="ml-3">
              <h3 class="text-sm font-medium text-red-800 dark:text-red-200">
                Alpha Testing Version
              </h3>
              <p class="mt-1 text-sm text-red-700 dark:text-red-300">
                This is an early version of ItsMint. You may encounter bugs or
                incomplete features. Your feedback is invaluable!
              </p>
            </div>
          </div>
        </div>

        <!-- Terms Notice -->
        <div class="mt-6 text-center">
          <p class="text-xs text-gray-500 dark:text-gray-400">
            By creating an account, you agree to our
            <a
              href="#"
              class="text-red-600 hover:text-orange-600 dark:text-red-400 dark:hover:text-orange-400"
              >Terms of Service</a
            >
            and
            <a
              href="#"
              class="text-red-600 hover:text-orange-600 dark:text-red-400 dark:hover:text-orange-400"
              >Privacy Policy</a
            >
          </p>
        </div>
      </div>

      <!-- Footer -->
      <div class="mt-8 text-center">
        <p class="text-xs text-gray-500 dark:text-gray-400">
          © 2024 ItsMint TCG Collection. All rights reserved.
        </p>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Custom input styling */
input:focus {
  box-shadow: 0 0 0 3px rgba(147, 51, 234, 0.1);
}

/* Loading animation */
@keyframes pulse {
  0%,
  100% {
    opacity: 1;
  }
  50% {
    opacity: 0.7;
  }
}

.animate-pulse {
  animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
</style>
