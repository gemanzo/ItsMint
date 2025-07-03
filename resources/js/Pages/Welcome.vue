<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
  canLogin?: boolean;
  canRegister?: boolean;
}>();

const features = [
  {
    icon: '⚔️',
    title: 'Crew Portfolio Tracking',
    description:
      'Track your One Piece card collection value in real-time with accurate market pricing from the Grand Line.',
  },
  {
    icon: '🏴‍☠️',
    title: 'Pirate Card Database',
    description:
      'Search through thousands of One Piece trading cards featuring Straw Hats, Marines, and legendary pirates.',
  },
  {
    icon: '📈',
    title: 'Bounty Analytics',
    description:
      'Get insights into card market trends and make strategic decisions like a true pirate captain.',
  },
  {
    icon: '💎',
    title: 'Devil Fruit Rarity Tracking',
    description:
      'Manage cards by rarity and condition, from common crew members to legendary Devil Fruit users.',
  },
  {
    icon: '🗺️',
    title: 'Set Completion Goals',
    description:
      'Chart your course to complete card sets and track your journey across different One Piece arcs.',
  },
  {
    icon: '📱',
    title: 'Adventure Ready',
    description:
      'Access your collection anywhere on your Grand Line adventure with full mobile support.',
  },
];

const testimonials = [
  {
    name: 'Monkey D. Luffy',
    role: 'Pirate King Aspirant',
    avatar: '👑',
    quote:
      'ItsMint helped me organize my card collection better than Nami organizes our treasure! Now I can track every rare card like a true captain!',
  },
  {
    name: 'Roronoa Zoro',
    role: 'Swordsman Collector',
    avatar: '⚔️',
    quote:
      'Even with my terrible sense of direction, I can easily navigate through my One Piece card collection with ItsMint. Three-sword style approved!',
  },
  {
    name: 'Nami',
    role: 'Treasure Navigator',
    avatar: '🗺️',
    quote:
      'The portfolio analytics helped me optimize my collection value by 300%! Now my cards are worth more than our stolen treasure!',
  },
];
</script>

<template>
  <Head title="ItsMint - One Piece TCG Collection Manager" />

  <div
    class="min-h-screen bg-gradient-to-br from-red-50 via-orange-50 to-yellow-50 dark:from-gray-900 dark:via-red-900 dark:to-yellow-900"
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

    <!-- Navigation -->
    <nav
      class="relative z-50 bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm border-b border-orange-200 dark:border-red-700 sticky top-0"
    >
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center py-4">
          <!-- Logo -->
          <div class="flex items-center space-x-3">
            <div class="relative">
              <div
                class="w-10 h-10 bg-gradient-to-r from-red-600 to-orange-600 rounded-xl shadow-lg transform rotate-3 absolute -top-0.5 -left-0.5"
              />
              <div
                class="w-10 h-10 bg-gradient-to-r from-orange-600 to-yellow-500 rounded-xl shadow-lg transform -rotate-2 absolute top-0 left-0"
              />
              <div
                class="w-10 h-10 bg-gradient-to-r from-red-500 to-yellow-400 rounded-xl shadow-xl relative z-10 flex items-center justify-center"
              >
                <svg
                  class="w-5 h-5 text-white"
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
            <div>
              <h1
                class="text-2xl font-bold bg-gradient-to-r from-red-600 via-orange-600 to-yellow-600 bg-clip-text text-transparent"
              >
                ItsMint
              </h1>
              <p class="text-xs text-gray-600 dark:text-gray-400 -mt-1">
                One Piece TCG Collection
              </p>
            </div>
          </div>

          <!-- Auth Links -->
          <div v-if="canLogin" class="flex items-center space-x-4">
            <Link
              v-if="$page.props.auth.user"
              :href="route('dashboard')"
              class="bg-gradient-to-r from-red-600 to-orange-600 hover:from-red-700 hover:to-orange-700 text-white px-6 py-2 rounded-lg font-medium transition-all duration-200 transform hover:scale-105"
            >
              Go to Dashboard
            </Link>
            <template v-else>
              <Link
                :href="route('login')"
                class="text-gray-700 dark:text-gray-300 hover:text-red-600 dark:hover:text-red-400 px-4 py-2 rounded-lg font-medium transition-colors"
              >
                Sign In
              </Link>
              <Link
                v-if="canRegister"
                :href="route('register')"
                class="bg-gradient-to-r from-red-600 to-orange-600 hover:from-red-700 hover:to-orange-700 text-white px-6 py-2 rounded-lg font-medium transition-all duration-200 transform hover:scale-105"
              >
                Get Started
              </Link>
            </template>
          </div>
        </div>
      </div>
    </nav>

    <!-- Hero Section -->
    <section class="relative py-20 lg:py-32">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center">
          <!-- Large Hero Logo -->
          <div class="flex justify-center mb-8">
            <div class="relative group">
              <div
                class="w-24 h-24 bg-gradient-to-r from-red-600 to-orange-600 rounded-2xl shadow-2xl transform rotate-3 absolute -top-2 -left-2 group-hover:rotate-6 transition-transform duration-300"
              />
              <div
                class="w-24 h-24 bg-gradient-to-r from-orange-600 to-yellow-500 rounded-2xl shadow-2xl transform -rotate-2 absolute top-0 left-0 group-hover:-rotate-4 transition-transform duration-300"
              />
              <div
                class="w-24 h-24 bg-gradient-to-r from-red-500 to-yellow-400 rounded-2xl shadow-3xl relative z-10 flex items-center justify-center group-hover:scale-110 transition-transform duration-300"
              >
                <svg
                  class="w-12 h-12 text-white"
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
          </div>

          <!-- Hero Title -->
          <h1
            class="text-5xl lg:text-7xl font-bold text-gray-900 dark:text-white mb-6"
          >
            Set Sail with Your
            <span
              class="bg-gradient-to-r from-red-600 via-orange-600 to-yellow-600 bg-clip-text text-transparent"
            >
              One Piece TCG
            </span>
            Collection
          </h1>

          <!-- Hero Subtitle -->
          <p
            class="text-xl lg:text-2xl text-gray-600 dark:text-gray-400 mb-8 max-w-3xl mx-auto leading-relaxed"
          >
            Navigate the Grand Line of card collecting! Track, analyze, and
            treasure your One Piece trading cards with the ultimate pirate's
            tool for portfolio management.
          </p>

          <!-- CTA Buttons -->
          <div class="flex flex-col sm:flex-row gap-4 justify-center mb-16">
            <Link
              v-if="!$page.props.auth.user && canRegister"
              :href="route('register')"
              class="bg-gradient-to-r from-red-600 to-orange-600 hover:from-red-700 hover:to-orange-700 text-white px-8 py-4 rounded-xl font-semibold text-lg shadow-lg transform transition-all duration-200 hover:scale-105 hover:shadow-xl"
            >
              Start Your Pirate Journey
            </Link>
            <Link
              v-if="$page.props.auth.user"
              :href="route('dashboard')"
              class="bg-gradient-to-r from-red-600 to-orange-600 hover:from-red-700 hover:to-orange-700 text-white px-8 py-4 rounded-xl font-semibold text-lg shadow-lg transform transition-all duration-200 hover:scale-105 hover:shadow-xl"
            >
              Go to Dashboard
            </Link>
            <Link
              v-if="!$page.props.auth.user"
              :href="route('login')"
              class="border-2 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:border-red-500 hover:text-red-600 dark:hover:text-red-400 px-8 py-4 rounded-xl font-semibold text-lg transition-all duration-200 hover:bg-red-50 dark:hover:bg-red-900/20"
            >
              Sign In
            </Link>
          </div>

          <!-- Stats -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-4xl mx-auto">
            <div class="text-center">
              <div class="text-4xl font-bold text-red-600 dark:text-red-400">
                50K+
              </div>
              <div class="text-gray-600 dark:text-gray-400">
                One Piece Cards Tracked
              </div>
            </div>
            <div class="text-center">
              <div
                class="text-4xl font-bold text-orange-600 dark:text-orange-400"
              >
                ₿5M+
              </div>
              <div class="text-gray-600 dark:text-gray-400">
                Total Bounty Value
              </div>
            </div>
            <div class="text-center">
              <div
                class="text-4xl font-bold text-yellow-600 dark:text-yellow-400"
              >
                1000+
              </div>
              <div class="text-gray-600 dark:text-gray-400">Pirate Crews</div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Features Section -->
    <section class="py-20 bg-white/50 dark:bg-gray-800/50 backdrop-blur-sm">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
          <h2 class="text-4xl font-bold text-gray-900 dark:text-white mb-4">
            Everything a Pirate Needs
          </h2>
          <p class="text-xl text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
            Powerful tools forged for serious One Piece TCG collectors and
            treasure hunters
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          <div
            v-for="feature in features"
            :key="feature.title"
            class="bg-white dark:bg-gray-800 p-8 rounded-2xl shadow-lg border border-gray-200 dark:border-gray-700 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2"
          >
            <div class="text-4xl mb-4">
              {{ feature.icon }}
            </div>
            <h3
              class="text-xl font-semibold text-gray-900 dark:text-white mb-3"
            >
              {{ feature.title }}
            </h3>
            <p class="text-gray-600 dark:text-gray-400">
              {{ feature.description }}
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- Testimonials Section -->
    <section class="py-20">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
          <h2 class="text-4xl font-bold text-gray-900 dark:text-white mb-4">
            Trusted by the Straw Hat Crew
          </h2>
          <p class="text-xl text-gray-600 dark:text-gray-400">
            See what our legendary crew members have to say
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
          <div
            v-for="testimonial in testimonials"
            :key="testimonial.name"
            class="bg-white dark:bg-gray-800 p-8 rounded-2xl shadow-lg border border-gray-200 dark:border-gray-700"
          >
            <div class="flex items-center mb-4">
              <div class="text-3xl mr-4">
                {{ testimonial.avatar }}
              </div>
              <div>
                <div class="font-semibold text-gray-900 dark:text-white">
                  {{ testimonial.name }}
                </div>
                <div class="text-sm text-gray-600 dark:text-gray-400">
                  {{ testimonial.role }}
                </div>
              </div>
            </div>
            <p class="text-gray-700 dark:text-gray-300 italic">
              "{{ testimonial.quote }}"
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA Section -->
    <section
      class="py-20 bg-gradient-to-r from-red-600 via-orange-600 to-yellow-600"
    >
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-4xl lg:text-5xl font-bold text-white mb-6">
          Ready to Find the One Piece?
        </h2>
        <p class="text-xl text-orange-100 mb-8 max-w-2xl mx-auto">
          Join thousands of pirates who trust ItsMint to manage their One Piece
          TCG treasure
        </p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
          <Link
            v-if="!$page.props.auth.user && canRegister"
            :href="route('register')"
            class="bg-white text-red-600 hover:bg-gray-100 px-8 py-4 rounded-xl font-semibold text-lg shadow-lg transform transition-all duration-200 hover:scale-105"
          >
            Join the Crew
          </Link>
          <Link
            v-if="$page.props.auth.user"
            :href="route('dashboard')"
            class="bg-white text-red-600 hover:bg-gray-100 px-8 py-4 rounded-xl font-semibold text-lg shadow-lg transform transition-all duration-200 hover:scale-105"
          >
            Access Dashboard
          </Link>
          <Link
            v-if="!$page.props.auth.user"
            :href="route('login')"
            class="border-2 border-white text-white hover:bg-white hover:text-red-600 px-8 py-4 rounded-xl font-semibold text-lg transition-all duration-200"
          >
            Sign In
          </Link>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-12">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
          <!-- Logo Column -->
          <div class="col-span-1 md:col-span-2">
            <div class="flex items-center space-x-3 mb-4">
              <div class="relative">
                <div
                  class="w-10 h-10 bg-gradient-to-r from-red-600 to-orange-600 rounded-xl shadow-lg transform rotate-3 absolute -top-0.5 -left-0.5"
                />
                <div
                  class="w-10 h-10 bg-gradient-to-r from-orange-600 to-yellow-500 rounded-xl shadow-lg transform -rotate-2 absolute top-0 left-0"
                />
                <div
                  class="w-10 h-10 bg-gradient-to-r from-red-500 to-yellow-400 rounded-xl shadow-xl relative z-10 flex items-center justify-center"
                >
                  <svg
                    class="w-5 h-5 text-white"
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
              <div>
                <h1
                  class="text-2xl font-bold bg-gradient-to-r from-red-400 via-orange-400 to-yellow-400 bg-clip-text text-transparent"
                >
                  ItsMint
                </h1>
                <p class="text-xs text-gray-400 -mt-1">
                  One Piece TCG Collection
                </p>
              </div>
            </div>
            <p class="text-gray-400 max-w-md">
              The ultimate One Piece TCG collection management tool for pirates
              sailing the Grand Line in search of legendary cards.
            </p>
          </div>

          <!-- Quick Links -->
          <div>
            <h3 class="font-semibold mb-4">Quick Links</h3>
            <div class="space-y-2">
              <Link
                v-if="!$page.props.auth.user"
                :href="route('register')"
                class="block text-gray-400 hover:text-white transition-colors"
              >
                Sign Up
              </Link>
              <Link
                v-if="!$page.props.auth.user"
                :href="route('login')"
                class="block text-gray-400 hover:text-white transition-colors"
              >
                Sign In
              </Link>
              <Link
                v-if="$page.props.auth.user"
                :href="route('dashboard')"
                class="block text-gray-400 hover:text-white transition-colors"
              >
                Dashboard
              </Link>
            </div>
          </div>

          <!-- Support -->
          <div>
            <h3 class="font-semibold mb-4">Support</h3>
            <div class="space-y-2">
              <a
                href="#"
                class="block text-gray-400 hover:text-white transition-colors"
                >Help Center</a
              >
              <a
                href="#"
                class="block text-gray-400 hover:text-white transition-colors"
                >Contact Us</a
              >
              <a
                href="#"
                class="block text-gray-400 hover:text-white transition-colors"
                >Privacy Policy</a
              >
              <a
                href="#"
                class="block text-gray-400 hover:text-white transition-colors"
                >Terms of Service</a
              >
            </div>
          </div>
        </div>

        <div class="border-t border-gray-800 mt-8 pt-8 text-center">
          <p class="text-gray-400">
            © 2025 ItsMint TCG Collection. All rights reserved.
          </p>
        </div>
      </div>
    </footer>
  </div>
</template>

<style scoped>
/* Custom animations */
@keyframes float {
  0%,
  100% {
    transform: translateY(0px);
  }
  50% {
    transform: translateY(-10px);
  }
}

.float {
  animation: float 6s ease-in-out infinite;
}

/* Shadow utilities */
.shadow-3xl {
  box-shadow: 0 35px 60px -12px rgba(0, 0, 0, 0.25);
}
</style>
