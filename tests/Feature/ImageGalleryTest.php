<?php

use App\Models\Image;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;

beforeEach(function () {
    // Clear images before each test
    Image::truncate();
});

test('image gallery displays pagination when there are many images', function () {
    // Create 25 images (more than 12 per page)
    Image::factory()->count(25)->create([
        'status' => 'completed',
    ]);

    Volt::test('image-gallery')
        ->assertSee('AI-Generated Images')
        ->assertSee('25 AI-generated images');
});

test('retry button appears for failed images', function () {
    // Create a failed image
    $failedImage = Image::factory()->create([
        'status' => 'failed',
        'error_message' => 'Google Gemini API error',
    ]);

    Volt::test('image-gallery')
        ->assertSee('Generation failed')
        ->assertSee('Try Again')
        ->assertSee('Google Gemini API error');
});

test('clicking retry button resets image status and dispatches job', function () {
    // Create a failed image
    $failedImage = Image::factory()->create([
        'status' => 'failed',
        'error_message' => 'API error',
    ]);

    Queue::fake();

    Volt::test('image-gallery')
        ->call('retryImage', $failedImage)
        ->assertHasNoErrors();

    // Check that the image status was reset
    $failedImage->refresh();
    expect($failedImage->status)->toBe('pending');
    expect($failedImage->error_message)->toBeNull();

    // Check that the job was dispatched
    Queue::assertPushed(\App\Jobs\GenerateImageJob::class);
});

test('pagination works correctly', function () {
    // Create exactly 24 images (2 pages of 12)
    Image::factory()->count(24)->create([
        'status' => 'completed',
    ]);

    $component = Volt::test('image-gallery');

    // First page should show 12 images
    $component->assertViewHas('images', function ($images) {
        return $images->count() === 12;
    });

    // Navigate to page 2
    $component->call('gotoPage', 2)
        ->assertViewHas('images', function ($images) {
            return $images->count() === 12;
        });
});

test('empty state is shown when no images exist', function () {
    Volt::test('image-gallery')
        ->assertSee('No images yet')
        ->assertSee('Take a photo to get started!');
});

test('processing indicator shows for pending and processing images', function () {
    Image::factory()->count(2)->create(['status' => 'pending']);
    Image::factory()->count(3)->create(['status' => 'processing']);

    Volt::test('image-gallery')
        ->assertSee('AI is generating 5')
        ->assertSee('images')
        ->assertSee('This may take a moment...');
});
