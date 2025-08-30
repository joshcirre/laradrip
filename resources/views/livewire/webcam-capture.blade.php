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
    public $isGeneratingPreview = false;
    public $previewImage = '';
    public $previewMode = false;

    public function startCapture(): void
    {
        $this->isCapturing = true;
        $this->capturedImage = '';
    }

    public function stopCamera(): void
    {
        $this->isCapturing = false;
        $this->capturedImage = '';
        $this->previewImage = '';
        $this->previewMode = false;
        $this->dispatch('stop-camera');
    }

    public function retakePhoto(): void
    {
        $this->capturedImage = '';
        $this->previewImage = '';
        $this->previewMode = false;
        $this->isGeneratingPreview = false;
        $this->isProcessing = false;
        $this->isCapturing = false;
        
        // Use a small delay to ensure DOM updates before restarting
        $this->dispatch('restart-capture');
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
    
    public function generatePreview(): void
    {
        try {
            if (!$this->capturedImage) {
                return;
            }
            
            $this->isGeneratingPreview = true;
            
            // Convert base64 to image file
            $imageData = str_replace('data:image/jpeg;base64,', '', $this->capturedImage);
            $imageData = str_replace(' ', '+', $imageData);
            $decodedImage = base64_decode($imageData);
            
            // Save temporary webcam image
            $tempFileName = 'temp/preview_' . uniqid() . '.jpg';
            Storage::put($tempFileName, $decodedImage);
            
            // Generate preview using Gemini service
            $geminiService = app(\App\Services\GeminiService::class);
            $generatedPath = $geminiService->generateImageWithModifications($tempFileName);
            
            if ($generatedPath) {
                // Convert generated image to base64 for preview
                $generatedContent = Storage::get($generatedPath);
                $this->previewImage = 'data:image/png;base64,' . base64_encode($generatedContent);
                $this->previewMode = true;
                
                // Clean up temporary files
                Storage::delete([$tempFileName, $generatedPath]);
                
                Flux::toast(
                    heading: 'Preview generated!',
                    text: 'You can save it or try again.',
                    variant: 'success'
                );
            } else {
                throw new \Exception('Failed to generate preview');
            }
            
            $this->isGeneratingPreview = false;
            
        } catch (\Exception $e) {
            $this->isGeneratingPreview = false;
            
            Flux::toast(
                text: 'Failed to generate preview: ' . $e->getMessage(),
                variant: 'danger'
            );
        }
    }
    
    public function savePreview(): void
    {
        try {
            if (!$this->capturedImage || !$this->previewImage) {
                return;
            }
            
            $this->isProcessing = true;
            
            // Save original webcam image
            $webcamData = str_replace('data:image/jpeg;base64,', '', $this->capturedImage);
            $webcamData = str_replace(' ', '+', $webcamData);
            $decodedWebcam = base64_decode($webcamData);
            $webcamFileName = 'images/webcam_' . uniqid() . '.jpg';
            Storage::put($webcamFileName, $decodedWebcam);
            
            // Save generated preview image
            $previewData = str_replace('data:image/png;base64,', '', $this->previewImage);
            $previewData = str_replace(' ', '+', $previewData);
            $decodedPreview = base64_decode($previewData);
            $generatedFileName = 'images/generated_' . uniqid() . '.png';
            Storage::put($generatedFileName, $decodedPreview);
            
            // Create image record with both paths
            $image = Image::create([
                'webcam_image_path' => $webcamFileName,
                'generated_image_path' => $generatedFileName,
                'prompt' => config('services.gemini.prompt'),
                'status' => 'completed',
            ]);
            
            // Reset state
            $this->isProcessing = false;
            $this->capturedImage = '';
            $this->previewImage = '';
            $this->previewMode = false;
            $this->isCapturing = false;
            
            // Notify gallery to refresh
            $this->dispatch('photo-captured');
            
            Flux::toast(
                heading: 'Image saved!',
                text: 'Your AI-generated image has been saved.',
                variant: 'success'
            );
            
        } catch (\Exception $e) {
            $this->isProcessing = false;
            
            Flux::toast(
                text: 'Failed to save image: ' . $e->getMessage(),
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
                                style="transform: scaleX(-1);"
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
                @elseif ($previewMode && $previewImage)
                    <div class="relative" style="aspect-ratio: 4/3;">
                        <img 
                            id="preview-image"
                            src="{{ $previewImage }}" 
                            alt="AI Preview" 
                            class="w-full h-full object-cover rounded-lg border border-gray-300"
                            style="-webkit-touch-callout: default;"
                        />
                        <flux:badge variant="primary" class="absolute top-2 right-2 pointer-events-none">
                            AI Preview
                        </flux:badge>
                        <div class="absolute bottom-2 left-2 pointer-events-none">
                            <img 
                                src="{{ $capturedImage }}" 
                                alt="Original" 
                                class="w-20 h-20 object-cover rounded-lg border-2 border-white shadow-lg"
                            />
                        </div>
                    </div>
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
                @elseif ($isGeneratingPreview)
                    <div class="absolute inset-0 bg-black/50 rounded-lg flex items-center justify-center">
                        <div class="text-center text-white">
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-white mx-auto mb-2"></div>
                            <p class="text-sm font-medium">Generating AI preview...</p>
                            <p class="text-xs mt-1 opacity-75">This may take a moment</p>
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
            @elseif ($capturedImage && !$previewMode)
                <div class="flex flex-col gap-3 w-full max-w-md">
                    <div class="flex gap-3">
                        <flux:button 
                            variant="ghost" 
                            icon="arrow-path" 
                            wire:click="retakePhoto"
                            :disabled="$isProcessing || $isGeneratingPreview"
                        >
                            Retake
                        </flux:button>
                        <flux:button 
                            variant="primary" 
                            icon="check" 
                            wire:click="processPhoto"
                            :disabled="$isProcessing || $isGeneratingPreview"
                            wire:loading.attr="disabled"
                            class="flex-1"
                        >
                            <span wire:loading.remove wire:target="processPhoto">Generate & Save</span>
                            <span wire:loading wire:target="processPhoto">Processing...</span>
                        </flux:button>
                    </div>
                    <div class="relative">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-300"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="px-2 bg-gray-100 text-gray-500">or</span>
                        </div>
                    </div>
                    <flux:button 
                        variant="ghost" 
                        icon="sparkles" 
                        wire:click="generatePreview"
                        :disabled="$isProcessing || $isGeneratingPreview"
                        wire:loading.attr="disabled"
                        class="w-full"
                    >
                        <span wire:loading.remove wire:target="generatePreview">Generate Preview Without Saving</span>
                        <span wire:loading wire:target="generatePreview">Generating Preview...</span>
                    </flux:button>
                </div>
            @elseif ($previewMode)
                <div class="flex flex-col gap-3 w-full max-w-md">
                    <div class="flex gap-3">
                        <flux:button 
                            variant="ghost" 
                            icon="arrow-path" 
                            wire:click="retakePhoto"
                            :disabled="$isProcessing"
                        >
                            Try Another
                        </flux:button>
                        <flux:button 
                            variant="primary" 
                            icon="check" 
                            wire:click="savePreview"
                            :disabled="$isProcessing"
                            wire:loading.attr="disabled"
                            class="flex-1"
                        >
                            <span wire:loading.remove wire:target="savePreview">Save to Gallery</span>
                            <span wire:loading wire:target="savePreview">Saving...</span>
                        </flux:button>
                    </div>
                    <flux:button 
                        variant="ghost" 
                        icon="arrow-down-tray" 
                        onclick="downloadPreviewImage()"
                        class="w-full hidden sm:block"
                    >
                        Download Image
                    </flux:button>
                </div>
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
            } else {
                stopStream();
            }
        });
        
        // Listen for stop camera event
        $wire.on('stop-camera', () => {
            stopStream();
        });
        
        // Listen for restart capture event
        $wire.on('restart-capture', () => {
            setTimeout(() => {
                $wire.startCapture();
            }, 100);
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
                
                // The video element shows a mirrored view via CSS transform
                // But the actual video stream data is not mirrored
                // So we draw it normally to get the un-mirrored (correct) orientation
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
        
        // Download preview image function
        window.downloadPreviewImage = function() {
            const img = document.getElementById('preview-image');
            if (img) {
                const link = document.createElement('a');
                link.href = img.src;
                link.download = 'ai-generated-' + Date.now() + '.png';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        }
    </script>
    @endscript
</div>