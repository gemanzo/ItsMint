<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ItsMint - Under Maintenance</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- TailwindCSS -->
    @vite(['resources/css/app.css'])

    <style>
        @keyframes float {

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        @keyframes wave {

            0%,
            100% {
                transform: rotate(0deg);
            }

            25% {
                transform: rotate(5deg);
            }

            75% {
                transform: rotate(-5deg);
            }
        }

        .float-animation {
            animation: float 3s ease-in-out infinite;
        }

        .wave-animation {
            animation: wave 2s ease-in-out infinite;
        }
    </style>
</head>

<body class="antialiased">
    <div class="min-h-screen bg-gradient-to-br from-red-50 via-orange-50 to-yellow-50 dark:from-gray-900 dark:via-red-900 dark:to-yellow-900">
        <!-- Background Pattern -->
        <div class="absolute inset-0 opacity-5">
            <div class="absolute inset-0" style="
                background-image:
                    radial-gradient(circle at 25px 25px, #dc2626 2px, transparent 0),
                    radial-gradient(circle at 75px 75px, #f59e0b 2px, transparent 0);
                background-size: 100px 100px;
            "></div>
        </div>

        <!-- Main Content -->
        <div class="relative z-10 flex min-h-screen items-center justify-center px-4">
            <div class="text-center max-w-md mx-auto">

                <!-- Animated Logo -->
                <div class="flex justify-center mb-8">
                    <div class="relative group float-animation">
                        <div class="w-20 h-20 bg-gradient-to-r from-red-600 to-orange-600 rounded-2xl shadow-2xl transform rotate-3 absolute -top-2 -left-2 group-hover:rotate-6 transition-transform duration-300"></div>
                        <div class="w-20 h-20 bg-gradient-to-r from-orange-600 to-yellow-500 rounded-2xl shadow-2xl transform -rotate-2 absolute top-0 left-0 group-hover:-rotate-4 transition-transform duration-300"></div>
                        <div class="w-20 h-20 bg-gradient-to-r from-red-500 to-yellow-400 rounded-2xl shadow-3xl relative z-10 flex items-center justify-center">
                            <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14-7H3a2 2 0 00-2 2v12a2 2 0 002 2h16a2 2 0 002-2V6a2 2 0 00-2-2z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Brand Name -->
                <div class="mb-8">
                    <h1 class="text-4xl font-bold bg-gradient-to-r from-red-600 via-orange-600 to-yellow-600 bg-clip-text text-transparent mb-2">
                        ItsMint
                    </h1>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        One Piece TCG Collection
                    </p>
                </div>

                <!-- Maintenance Icon -->
                <div class="mb-8 flex justify-center">
                    <div class="relative">
                        <div class="w-16 h-16 bg-orange-100 dark:bg-orange-900/30 rounded-full flex items-center justify-center wave-animation">
                            <svg class="w-8 h-8 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Maintenance Message -->
                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">
                        🚧 Grand Line Under Maintenance
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4 leading-relaxed">
                        Ahoy, Pirate! Our crew is currently working hard to upgrade the ship and enhance your card collection adventure.
                    </p>
                    <p class="text-gray-600 dark:text-gray-400 mb-6 leading-relaxed">
                        We'll be back soon with exciting new features to help you manage your One Piece TCG treasures!
                    </p>
                </div>

                <!-- Status and Contact -->
                <div class="space-y-4">
                    <div class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm rounded-xl p-6 border border-orange-200 dark:border-red-700">
                        <div class="flex items-center justify-center space-x-2 mb-3">
                            <div class="w-2 h-2 bg-orange-500 rounded-full animate-pulse"></div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                Status: Maintenance in Progress
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Expected completion: Soon™
                        </p>
                    </div>

                    <!-- Footer Message -->
                    <div class="text-center">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                            ⚓ Thank you for your patience, Nakama! ⚓
                        </p>
                        <p class="text-xs text-gray-400 dark:text-gray-500">
                            Keep sailing the Grand Line - we'll see you soon!
                        </p>
                    </div>
                </div>

                <!-- Decorative Elements -->
                <div class="absolute top-10 left-10 text-4xl opacity-20 transform rotate-12 wave-animation" style="animation-delay: -0.5s;">
                    🏴‍☠️
                </div>
                <div class="absolute bottom-10 right-10 text-3xl opacity-20 transform -rotate-12 float-animation" style="animation-delay: -1s;">
                    ⚔️
                </div>
                <div class="absolute top-1/4 right-16 text-2xl opacity-15 transform rotate-45 wave-animation" style="animation-delay: -1.5s;">
                    🗺️
                </div>
                <div class="absolute bottom-1/4 left-16 text-2xl opacity-15 transform -rotate-45 float-animation" style="animation-delay: -2s;">
                    💎
                </div>
            </div>
        </div>
    </div>
</body>

</html>