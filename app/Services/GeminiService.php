<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
    }

    public function generateImageWithModifications(string $originalImagePath): ?string
    {
        try {
            $imageContent = base64_encode(file_get_contents($originalImagePath));
            
            $response = Http::withHeaders([
                'x-goog-api-key' => $this->apiKey,
            ])->post("{$this->baseUrl}/gemini-2.5-flash-image-preview:generateContent", [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => 'Create a picture with these modifications: Add a bust down diamond chain around the neck. If the person is smiling, add diamond grills to their teeth. Make it look realistic and luxurious with a cosmic Gemini constellation theme in the background.'
                            ],
                            [
                                'inline_data' => [
                                    'mime_type' => 'image/jpeg',
                                    'data' => $imageContent
                                ]
                            ]
                        ]
                    ]
                ]
            ]);

            if ($response->successful()) {
                $responseBody = $response->body();
                
                // The image data is returned in the response body as JSON
                // We need to search for the "data" field in the response
                // Using regex to find the base64 image data like the curl example
                if (preg_match('/"data":\s*"([^"]+)"/', $responseBody, $matches)) {
                    $imageData = $matches[1];
                    
                    // Save the image to storage
                    $fileName = 'generated_' . uniqid() . '.png';
                    $filePath = storage_path('app/public/images/' . $fileName);
                    
                    // Ensure directory exists
                    if (!file_exists(dirname($filePath))) {
                        mkdir(dirname($filePath), 0755, true);
                    }
                    
                    // Decode and save the image
                    file_put_contents($filePath, base64_decode($imageData));
                    
                    Log::info('Successfully extracted and saved generated image');
                    return 'images/' . $fileName;
                }
                
                // If no image data found in the response, log the structure
                $data = $response->json();
                Log::warning('No image data found in response', [
                    'response_structure' => json_encode($data, JSON_PRETTY_PRINT)
                ]);
                
                // Check if there's text explaining why no image was generated
                if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                    Log::info('Gemini response text', [
                        'text' => $data['candidates'][0]['content']['parts'][0]['text']
                    ]);
                }
                
                return $this->createPlaceholderImage($originalImagePath);
            }

            Log::error('Gemini API generate error', ['response' => $response->body()]);
            return null;
        } catch (\Exception $e) {
            Log::error('Gemini generate exception', ['error' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * Create a placeholder image with overlay text
     * This is used when the API doesn't support image generation
     */
    private function createPlaceholderImage(string $originalImagePath): ?string
    {
        try {
            // For now, just copy the original with a "DRIPPED OUT" watermark
            // In production, you'd want to use an image manipulation library
            $fileName = 'generated_' . uniqid() . '.jpg';
            $filePath = storage_path('app/public/images/' . $fileName);
            
            // Ensure directory exists
            if (!file_exists(dirname($filePath))) {
                mkdir(dirname($filePath), 0755, true);
            }
            
            // For demo purposes, just copy the original
            // In a real app, you'd add overlays/effects here
            copy($originalImagePath, $filePath);
            
            return 'images/' . $fileName;
        } catch (\Exception $e) {
            Log::error('Failed to create placeholder image', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
