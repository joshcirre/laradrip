<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laravel Drip Me Out - AI Diamond Chain Generator</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-red-50">
    <div class="flex flex-col w-full min-h-screen p-4 lg:p-6">
        <!-- Header -->
        <div class="flex flex-col items-start justify-start gap-2 w-full mb-6 sm:mb-8 lg:mb-10">
            <div class="flex items-center gap-3">
                <h1 class="text-2xl sm:text-3xl lg:text-4xl font-semibold text-gray-900">
                    Laravel Drip Me Out
                </h1>
                <a href="https://github.com/yourusername/laradrip" target="_blank" rel="noopener noreferrer"
                   class="text-gray-500 hover:text-gray-700 transition-colors"
                   aria-label="View source code on GitHub">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-6 h-6">
                        <path d="M15 22v-4a4.8 4.8 0 0 0-1-3.5c3 0 6-2 6-5.5.08-1.25-.27-2.48-1-3.5.28-1.15.28-2.35 0-3.5 0 0-1 0-3 1.5-2.64-.5-5.36-.5-8 0C6 2 5 2 5 2c-.3 1.15-.3 2.35 0 3.5A5.403 5.403 0 0 0 4 9c0 3.5 3 5.5 6 5.5-.39.49-.68 1.05-.85 1.65-.17.6-.22 1.23-.15 1.85v4"></path>
                        <path d="M9 18c-4.51 2-5-2-7-2"></path>
                    </svg>
                </a>
            </div>
            <p class="text-sm text-gray-600">
                Upload an image or capture a photo to see what you look like with a diamond chain.
            </p>
        </div>

        <!-- Main Content Grid -->
        <div class="w-full">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-8">
                <!-- Left Column: Camera -->
                <div class="lg:max-w-2xl w-full">
                    <livewire:webcam-capture />
                </div>

                <!-- Right Column: Gallery -->
                <div class="w-full">
                    <livewire:image-gallery />
                </div>
            </div>
        </div>
    </div>

    <flux:toast.group position="bottom end" expanded>
        <flux:toast />
    </flux:toast.group>

    @livewireScripts
</body>
</html>
