<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Checkbox from '@/Components/Checkbox.vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    terms_accepted: false,
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

    <div class="min-h-screen bg-gradient-to-br from-red-50 via-orange-50 to-yellow-50 dark:from-gray-900 dark:via-red-900 dark:to-yellow-900 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <!-- Background Pattern -->
        <div class="absolute inset-0 opacity-5">
            <div class="absolute inset-0" style="background-image: radial-gradient(circle at 25px 25px, #dc2626 2px, transparent 0), radial-gradient(circle at 75px 75px, #f59e0b 2px, transparent 0); background-size: 100px 100px;"></div>
        </div>
        
        <div class="relative sm:mx-auto sm:w-full sm:max-w-md">
            <!-- Large Logo Section -->
            <div class="text-center mb-8">
                <Link href="/" class="inline-block group">
                    <!-- Enhanced Large Logo -->
                    <div class="flex items-center justify-center space-x-3 mb-4 transform group-hover:scale-105 transition-transform duration-300">
                        <!-- Card Stack Icon - Larger -->
                        <div class="relative">
                            <div class="w-16 h-16 bg-gradient-to-r from-red-600 to-orange-600 rounded-xl shadow-lg transform rotate-3 absolute -top-1 -left-1"></div>
                            <div class="w-16 h-16 bg-gradient-to-r from-orange-600 to-yellow-500 rounded-xl shadow-lg transform -rotate-2 absolute top-0 left-0"></div>
                            <div class="w-16 h-16 bg-gradient-to-r from-red-500 to-yellow-400 rounded-xl shadow-xl relative z-10 flex items-center justify-center">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14-7H3a2 2 0 00-2 2v12a2 2 0 002 2h16a2 2 0 002-2V6a2 2 0 00-2-2z"></path>
                                </svg>
                            </div>
                        </div>
                        <!-- Text Logo -->
                        <div>
                            <h1 class="text-4xl font-bold bg-gradient-to-r from-red-600 via-orange-600 to-yellow-600 bg-clip-text text-transparent">
                                ItsMint
                            </h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 font-medium tracking-wider">
                                One Piece TCG Collection
                            </p>
                        </div>
                    </div>
                </Link>
                
                <!-- Welcome Message -->
                <div class="space-y-2">
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                        Join ItsMint Today!
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400">
                        Start your One Piece TCG collection journey
                    </p>
                </div>
            </div>

            <!-- Register Card -->
            <div class="bg-white dark:bg-gray-800 py-8 px-6 shadow-2xl rounded-2xl border border-gray-200 dark:border-gray-700 backdrop-blur-sm bg-opacity-95 dark:bg-opacity-95">
                
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Name Field -->
                    <div>
                        <InputLabel for="name" value="Full Name" class="text-sm font-medium text-gray-700 dark:text-gray-300" />
                        <div class="mt-2 relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </div>
                            <TextInput
                                id="name"
                                type="text"
                                class="block w-full pl-10 pr-3 py-3 border border-gray-300 dark:border-gray-600 rounded-xl shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent text-sm bg-white dark:bg-gray-700"
                                v-model="form.name"
                                required
                                autofocus
                                autocomplete="name"
                                placeholder="Enter your full name"
                            />
                        </div>
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <!-- Email Field -->
                    <div>
                        <InputLabel for="email" value="Email Address" class="text-sm font-medium text-gray-700 dark:text-gray-300" />
                        <div class="mt-2 relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path>
                                </svg>
                            </div>
                            <TextInput
                                id="email"
                                type="email"
                                class="block w-full pl-10 pr-3 py-3 border border-gray-300 dark:border-gray-600 rounded-xl shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent text-sm bg-white dark:bg-gray-700"
                                v-model="form.email"
                                required
                                autocomplete="username"
                                placeholder="Enter your email address"
                            />
                        </div>
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <!-- Password Field -->
                    <div>
                        <InputLabel for="password" value="Password" class="text-sm font-medium text-gray-700 dark:text-gray-300" />
                        <div class="mt-2 relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                </svg>
                            </div>
                            <TextInput
                                id="password"
                                :type="showPassword ? 'text' : 'password'"
                                class="block w-full pl-10 pr-12 py-3 border border-gray-300 dark:border-gray-600 rounded-xl shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent text-sm bg-white dark:bg-gray-700"
                                v-model="form.password"
                                required
                                autocomplete="new-password"
                                placeholder="Create a strong password"
                            />
                            <button
                                type="button"
                                @click="togglePasswordVisibility"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                            >
                                <svg v-if="!showPassword" class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                                <svg v-else class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"></path>
                                </svg>
                            </button>
                        </div>
                        
                        <!-- Password Strength Indicator -->
                        <div v-if="form.password" class="mt-2">
                            <div class="flex items-center justify-between text-xs mb-1">
                                <span class="text-gray-500 dark:text-gray-400">Password strength:</span>
                                <span :class="`font-medium text-${passwordStrength.color}-600 dark:text-${passwordStrength.color}-400`">
                                    {{ passwordStrength.text }}
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                                <div 
                                    :class="`h-1.5 rounded-full transition-all duration-300 bg-${passwordStrength.color}-500`"
                                    :style="`width: ${(passwordStrength.level / 4) * 100}%`"
                                ></div>
                            </div>
                        </div>
                        
                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>

                    <!-- Confirm Password Field -->
                    <div>
                        <InputLabel for="password_confirmation" value="Confirm Password" class="text-sm font-medium text-gray-700 dark:text-gray-300" />
                        <div class="mt-2 relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <TextInput
                                id="password_confirmation"
                                :type="showPasswordConfirmation ? 'text' : 'password'"
                                class="block w-full pl-10 pr-12 py-3 border border-gray-300 dark:border-gray-600 rounded-xl shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent text-sm bg-white dark:bg-gray-700"
                                v-model="form.password_confirmation"
                                required
                                autocomplete="new-password"
                                placeholder="Confirm your password"
                            />
                            <button
                                type="button"
                                @click="togglePasswordConfirmationVisibility"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                            >
                                <svg v-if="!showPasswordConfirmation" class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                                <svg v-else class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"></path>
                                </svg>
                            </button>
                        </div>
                        <InputError class="mt-2" :message="form.errors.password_confirmation" />
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
                            <svg v-if="form.processing" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span v-if="form.processing">Creating account...</span>
                            <span v-else>Create Account</span>
                        </PrimaryButton>
                    </div>
                </form>

                <!-- Terms Notice -->
                <div class="mt-6 text-center">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        By creating an account, you agree to our 
                        <a href="#" class="text-red-600 hover:text-orange-600 dark:text-red-400 dark:hover:text-orange-400">Terms of Service</a> 
                        and 
                        <a href="#" class="text-red-600 hover:text-orange-600 dark:text-red-400 dark:hover:text-orange-400">Privacy Policy</a>
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
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: .7;
    }
}

.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
</style>
