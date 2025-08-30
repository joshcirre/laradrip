<?php

use App\Models\Image;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;
    
    public $perPage = 12;

    public function mount(): void
    {
        // Initial load happens via with() method
    }

    public function with(): array
    {
        return [
            'images' => Image::orderBy('created_at', 'desc')->paginate($this->perPage)
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

    public function loadMore(): void
    {
        $this->perPage += 12;
    }
}; ?>

<div class="w-full">
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900">AI-Generated Images</h2>
            @if($images->total() > 0)
                <p class="text-sm text-gray-600">
                    {{ $images->where('status', 'completed')->count() }} AI-generated images ✨
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
                    <div class="group" wire:key="image-{{ $image->id }}">
                        <div class="bg-white border border-gray-200 hover:border-gray-300 transition-colors overflow-hidden rounded-lg">
                            @if ($image->status === 'completed' && $image->generated_image_path)
                                <div class="aspect-square relative">
                                    <img 
                                        src="{{ asset('storage/' . $image->generated_image_path) }}" 
                                        alt="AI-generated image"
                                        class="w-full h-full object-cover transition-transform group-hover:scale-105"
                                        loading="lazy"
                                    >
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity">
                                        <div class="absolute bottom-4 left-4 right-4">
                                            <p class="text-white text-sm font-medium mb-2">Original photo:</p>
                                            <img 
                                                src="{{ asset('storage/' . $image->webcam_image_path) }}" 
                                                alt="Original"
                                                class="w-16 h-16 object-cover rounded-lg border-2 border-white/80"
                                            >
                                        </div>
                                    </div>
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
                                    <div class="absolute bottom-4 left-4">
                                        <img 
                                            src="{{ asset('storage/' . $image->webcam_image_path) }}" 
                                            alt="Processing"
                                            class="w-16 h-16 object-cover rounded-lg border-2 border-gray-200 opacity-75"
                                        >
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
                                    <div class="absolute bottom-4 left-4">
                                        <img 
                                            src="{{ asset('storage/' . $image->webcam_image_path) }}" 
                                            alt="Queued"
                                            class="w-16 h-16 object-cover rounded-lg border-2 border-gray-200 opacity-50"
                                        >
                                    </div>
                                </div>
                            @else
                                <div class="aspect-square bg-red-50 flex items-center justify-center">
                                    <div class="text-center px-4">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-10 h-10 mx-auto mb-3 text-red-500">
                                            <circle cx="12" cy="12" r="10"/>
                                            <path d="m15 9-6 6"/>
                                            <path d="m9 9 6 6"/>
                                        </svg>
                                        <p class="text-sm font-medium text-red-600">
                                            Generation failed
                                        </p>
                                        @if ($image->error_message)
                                            <p class="text-xs text-red-500 mt-1">
                                                {{ Str::limit($image->error_message, 50) }}
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            
            @if($images->hasMorePages())
                <div class="flex justify-center mt-6">
                    <flux:button variant="ghost" wire:click="loadMore" wire:loading.attr="disabled">
                        <span wire:loading.remove>Load More ({{ $images->total() - $images->count() }} remaining)</span>
                        <span wire:loading>Loading...</span>
                    </flux:button>
                </div>
            @endif
            
            @if($images->where('status', 'pending')->count() > 0 || $images->where('status', 'processing')->count() > 0)
                <div class="mt-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
                    <div class="flex items-center gap-2">
                        <div class="animate-spin rounded-full h-4 w-4 border-2 border-blue-600 border-t-transparent"></div>
                        <p class="text-sm text-blue-700">
                            AI is generating {{ $images->whereIn('status', ['pending', 'processing'])->count() }} 
                            {{ Str::plural('image', $images->whereIn('status', ['pending', 'processing'])->count()) }}. 
                            This may take a moment...
                        </p>
                    </div>
                </div>
            @endif
        @endif
    </div>
</div>