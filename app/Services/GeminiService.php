<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GeminiService
{
    protected string $apiKey;
    protected string $prompt;
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->prompt = config('services.gemini.prompt');
    }

    public function generateImageWithModifications(string $originalImagePath): ?string
    {
        try {
            // Read the image using Storage facade
            $imageContent = base64_encode(Storage::get($originalImagePath));
            
            $response = Http::withHeaders([
                'x-goog-api-key' => $this->apiKey,
            ])->post("{$this->baseUrl}/gemini-2.5-flash-image-preview:generateContent", [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => $this->prompt
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
                    
                    // Save the image using Storage facade
                    $fileName = 'images/generated_' . uniqid() . '.png';
                    
                    // Store the decoded image
                    Storage::put($fileName, base64_decode($imageData));
                    
                    Log::info('Successfully extracted and saved generated image');
                    return $fileName;
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
            // For demo purposes, just copy the original
            // In a real app, you'd add overlays/effects here
            $fileName = 'images/generated_' . uniqid() . '.jpg';
            
            // Copy using Storage facade
            Storage::put($fileName, Storage::get($originalImagePath));
            
            return $fileName;
        } catch (\Exception $e) {
            Log::error('Failed to create placeholder image', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
