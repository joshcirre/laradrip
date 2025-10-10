<?php

use App\Models\Image;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public function with(): array
    {
        return [
            'images' => Image::where('status', '!=', 'failed')->orderBy('created_at', 'desc')->paginate(12),
            'completedCount' => Image::where('status', 'completed')->count(),
            'processingCount' => Image::whereIn('status', ['pending', 'processing'])->count(),
        ];
    }

    #[On('echo:images,.ImageCreated')]
    public function onImageCreated($event): void
    {
        // Force a fresh query when a new image is created
        // The dot prefix indicates this is a model event
        $this->resetPage();
    }

    #[On('echo:images,.ImageUpdated')]
    public function onImageUpdated($event): void
    {
        // The with() method will automatically re-query when component refreshes
        // The dot prefix is required for model broadcast events
    }

    #[On('photo-captured')]
    public function onPhotoCaptured(): void
    {
        // Reset to first page when a new photo is captured
        $this->resetPage();
    }

}; ?>

<div class="w-full">
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900">AI-Generated Images</h2>
            @if($completedCount > 0)
                <p class="text-sm text-gray-600">
                    {{ $completedCount }} AI-generated images ✨
                </p>
            @endif
        </div>

        @if ($images->total() === 0)
            <div class="text-center py-12 bg-gray-50 rounded-lg border border-gray-200">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-16 h-16 mx-auto mb-4 text-gray-300">
                    <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
                    <circle cx="9" cy="9" r="2"/>
                    <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
                </svg>
                <p class="text-lg font-medium text-gray-600 mb-2">No images yet</p>
                <p class="text-sm text-gray-500">Take a photo to get started!</p>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($images as $image)
                    @if ($image->status !== 'failed')
                    <div class="group" wire:key="image-{{ $image->id }}">
                        <div class="bg-white border border-gray-200 hover:border-gray-300 transition-colors overflow-hidden rounded-lg">
                            @if ($image->status === 'completed' && $image->generated_image_path)
                                <div class="aspect-square relative">
                                    <img
                                        src="{{ Storage::url($image->generated_image_path) }}"
                                        alt="AI-generated image"
                                        class="w-full h-full object-cover transition-transform group-hover:scale-105"
                                        loading="lazy"
                                        style="-webkit-touch-callout: default;"
                                    >
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                                        <div class="absolute bottom-4 left-4 right-4">
                                            <p class="text-white text-sm font-medium mb-2">Original photo:</p>
                                            <img
                                                src="{{ Storage::url($image->webcam_image_path) }}"
                                                alt="Original"
                                                class="w-16 h-16 object-cover rounded-lg border-2 border-white/80"
                                            >
                                        </div>
                                    </div>
                                    <a 
                                        href="{{ Storage::url($image->generated_image_path) }}"
                                        download="ai-generated-{{ $image->id }}.png"
                                        class="absolute top-2 right-2 bg-white/90 backdrop-blur-sm rounded-lg p-2 opacity-0 group-hover:opacity-100 transition-opacity hidden sm:block"
                                        onclick="event.stopPropagation();"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-700">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                            <polyline points="7 10 12 15 17 10"/>
                                            <line x1="12" y1="15" x2="12" y2="3"/>
                                        </svg>
                                    </a>
                                </div>
                            @elseif ($image->status === 'processing')
                                <div class="aspect-square bg-gray-50 flex items-center justify-center relative">
                                    <div class="text-center">
                                        <div class="animate-spin rounded-full h-10 w-10 border-4 border-blue-600 border-t-transparent mx-auto mb-3"></div>
                                        <p class="text-sm font-medium text-gray-600">
                                            Generating AI magic...
                                        </p>
                                        <p class="text-xs text-gray-500 mt-1">This may take a moment</p>
                                    </div>
                                </div>
                            @elseif ($image->status === 'pending')
                                <div class="aspect-square bg-gray-50 flex items-center justify-center relative">
                                    <div class="text-center">
                                        <div class="flex space-x-1 justify-center mb-3">
                                            <div class="w-2 h-2 bg-blue-600 rounded-full animate-bounce" style="animation-delay: 0ms"></div>
                                            <div class="w-2 h-2 bg-blue-600 rounded-full animate-bounce" style="animation-delay: 150ms"></div>
                                            <div class="w-2 h-2 bg-blue-600 rounded-full animate-bounce" style="animation-delay: 300ms"></div>
                                        </div>
                                        <p class="text-sm font-medium text-gray-600">
                                            Queued for processing
                                        </p>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                    @endif
                @endforeach
            </div>

            @if($images->hasPages())
                <div class="mt-6">
                    {{ $images->links() }}
                </div>
            @endif

            @if($processingCount > 0)
                <div class="mt-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
                    <div class="flex items-center gap-2">
                        <div class="animate-spin rounded-full h-4 w-4 border-2 border-blue-600 border-t-transparent"></div>
                        <p class="text-sm text-blue-700">
                            AI is generating {{ $processingCount }}
                            {{ Str::plural('image', $processingCount) }}.
                            This may take a moment...
                        </p>
                    </div>
                </div>
            @endif
        @endif
    </div>
</div>
