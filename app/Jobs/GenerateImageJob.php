<?php

namespace App\Jobs;

use App\Models\Image;
use App\Services\GeminiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateImageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Image $image
    ) {}

    public function handle(GeminiService $geminiService): void
    {
        try {
            // Update status to processing
            $this->image->update(['status' => 'processing']);

            // Set the prompt for the modifications
            $prompt = 'Add a bust down diamond chain around the neck. If smiling, add diamond grills to the teeth. Add a orange Lambo in the background of the image.';
            $this->image->update(['prompt' => $prompt]);

            // Generate modified image using the webcam image
            $generatedImagePath = $geminiService->generateImageWithModifications(
                storage_path('app/public/' . $this->image->webcam_image_path)
            );

            if (!$generatedImagePath) {
                throw new \Exception('Failed to generate image');
            }

            // Update the image record with success
            $this->image->update([
                'generated_image_path' => $generatedImagePath,
                'status' => 'completed',
            ]);

        } catch (\Exception $e) {
            Log::error('Image generation failed', [
                'image_id' => $this->image->id,
                'error' => $e->getMessage()
            ]);

            // Update the image record with failure
            $this->image->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
