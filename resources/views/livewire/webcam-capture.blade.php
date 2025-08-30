<?php

use App\Jobs\GenerateImageJob;
use App\Models\Image;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;

new class extends Component {
    use WithFileUploads;

    public $isCapturing = false;
    public $capturedImage = '';
    public $isProcessing = false;

    public function startCapture(): void
    {
        $this->isCapturing = true;
        $this->capturedImage = '';
    }

    public function stopCamera(): void
    {
        $this->isCapturing = false;
        $this->capturedImage = '';
        $this->dispatch('stop-camera');
    }

    public function retakePhoto(): void
    {
        $this->capturedImage = '';
        $this->startCapture();
    }

    public function capturePhoto($imageData): void
    {
        // Just store the captured image, don't process yet
        $this->capturedImage = $imageData;
        $this->isProcessing = false;
    }
    
    public function processPhoto(): void
    {
        try {
            if (!$this->capturedImage) {
                return;
            }
            
            $this->isProcessing = true;
            
            // Convert base64 to image file
            $imageData = str_replace('data:image/jpeg;base64,', '', $this->capturedImage);
            $imageData = str_replace(' ', '+', $imageData);
            $decodedImage = base64_decode($imageData);
            
            // Save webcam image using Storage facade
            $fileName = 'images/webcam_' . uniqid() . '.jpg';
            
            // Store the image using Storage facade (works with S3 or any driver)
            Storage::put($fileName, $decodedImage);
            
            // Create image record
            $image = Image::create([
                'webcam_image_path' => $fileName,
                'status' => 'pending',
            ]);
            
            // Dispatch job to generate image
            GenerateImageJob::dispatch($image);
            
            // Reset state
            $this->isProcessing = false;
            $this->capturedImage = '';
            $this->isCapturing = false;
            
            // Notify gallery to refresh
            $this->dispatch('photo-captured');
            
            // Show success toast using Flux
            Flux::toast(
                heading: 'Photo submitted!',
                text: 'AI is now generating your diamond chain image.',
                variant: 'success'
            );
            
        } catch (\Exception $e) {
            $this->isProcessing = false;
            
            // Show error toast
            Flux::toast(
                text: 'Failed to process photo: ' . $e->getMessage(),
                variant: 'danger'
            );
        }
    }
}; ?>

<div class="w-full">
    <div class="space-y-4">
        <div class="bg-gray-100 rounded-lg p-4 border border-gray-200">
            <div class="relative">
                @if (!$capturedImage)
                    @if ($isCapturing)
                        <div style="aspect-ratio: 4/3;" class="relative">
                            <video 
                                id="webcam-video" 
                                autoplay 
                                playsinline 
                                muted
                                class="w-full h-full object-cover rounded-lg border border-gray-300"
                            ></video>
                        </div>
                    @else
                        <div class="w-full bg-white border border-gray-200 rounded-lg flex items-center justify-center" 
                             style="aspect-ratio: 4/3; min-height: 240px;">
                            <div class="text-center text-gray-500">
                                <flux:icon.camera class="w-16 h-16 mx-auto mb-3 text-gray-400" />
                                <p class="text-lg font-medium">Camera is off</p>
                                <p class="text-sm">Click "Start Camera" to begin</p>
                            </div>
                        </div>
                    @endif
                @else
                    <div class="relative" style="aspect-ratio: 4/3;">
                        <img 
                            src="{{ $capturedImage }}" 
                            alt="Captured photo" 
                            class="w-full h-full object-cover rounded-lg border border-gray-300"
                        />
                        <flux:badge variant="success" class="absolute top-2 right-2">
                            Captured
                        </flux:badge>
                    </div>
                @endif
                
                @if ($isProcessing)
                    <div class="absolute inset-0 bg-black/50 rounded-lg flex items-center justify-center">
                        <div class="text-center text-white">
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-white mx-auto mb-2"></div>
                            <p class="text-sm font-medium">Uploading...</p>
                        </div>
                    </div>
                @endif
            </div>
            <canvas id="webcam-canvas" class="hidden"></canvas>
        </div>

        <div class="flex justify-center gap-3">
            @if (!$isCapturing && !$capturedImage)
                <flux:button variant="primary" icon="camera" wire:click="startCapture">
                    Start Camera
                </flux:button>
            @elseif ($isCapturing && !$capturedImage)
                <flux:button 
                    variant="primary" 
                    icon="camera" 
                    id="capture-btn"
                    wire:loading.attr="disabled"
                    :disabled="$isProcessing"
                >
                    <span wire:loading.remove wire:target="capturePhoto">Capture Photo</span>
                    <span wire:loading wire:target="capturePhoto">Uploading...</span>
                </flux:button>
                <flux:button 
                    variant="ghost" 
                    icon="x-mark" 
                    wire:click="stopCamera"
                    :disabled="$isProcessing"
                >
                    Stop Camera
                </flux:button>
            @elseif ($capturedImage)
                <flux:button 
                    variant="ghost" 
                    icon="arrow-path" 
                    wire:click="retakePhoto"
                    :disabled="$isProcessing"
                >
                    Retake
                </flux:button>
                <flux:button 
                    variant="primary" 
                    icon="check" 
                    wire:click="processPhoto"
                    :disabled="$isProcessing"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove wire:target="processPhoto">Generate with AI</span>
                    <span wire:loading wire:target="processPhoto">Processing...</span>
                </flux:button>
            @endif
        </div>
    </div>

    @script
    <script>
        let stream = null;
        
        // Watch for isCapturing changes
        $wire.$watch('isCapturing', (value) => {
            if (value) {
                startWebcam();
            }
        });
        
        // Listen for stop camera event
        $wire.on('stop-camera', () => {
            stopStream();
        });
        
        async function startWebcam() {
            try {
                stream = await navigator.mediaDevices.getUserMedia({ 
                    video: { 
                        facingMode: 'user',
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    },
                    audio: false
                });
                
                // Wait for DOM to update
                setTimeout(() => {
                    const video = document.getElementById('webcam-video');
                    if (video && stream) {
                        video.srcObject = stream;
                    }
                }, 100);
            } catch (error) {
                console.error('Error accessing webcam:', error);
                alert('Unable to access camera. Please check permissions.');
                $wire.stopCamera();
            }
        }
        
        function stopStream() {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
            }
            const video = document.getElementById('webcam-video');
            if (video) {
                video.srcObject = null;
            }
        }
        
        // Capture photo button click
        document.addEventListener('click', function(e) {
            if (e.target.id === 'capture-btn' || e.target.closest('#capture-btn')) {
                e.preventDefault();
                capturePhoto();
            }
        });
        
        function capturePhoto() {
            const video = document.getElementById('webcam-video');
            const canvas = document.getElementById('webcam-canvas');
            
            if (video && canvas && video.readyState === 4) {
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                
                const context = canvas.getContext('2d');
                context.drawImage(video, 0, 0, canvas.width, canvas.height);
                
                // Convert to base64
                const imageData = canvas.toDataURL('image/jpeg', 0.8);
                
                // Stop webcam after capture
                stopStream();
                
                // Send to Livewire
                $wire.capturePhoto(imageData);
            }
        }
        
        // Cleanup on component destroy
        document.addEventListener('livewire:navigated', () => {
            stopStream();
        });
        
        // Initial check if capturing
        if ($wire.isCapturing) {
            startWebcam();
        }
    </script>
    @endscript
</div>