<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Image>
 */
class ImageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'prompt' => fake()->sentence(),
            'webcam_image_path' => 'images/webcam/'.fake()->uuid().'.jpg',
            'generated_image_path' => 'images/generated/'.fake()->uuid().'.jpg',
            'status' => fake()->randomElement(['pending', 'processing', 'completed', 'failed']),
            'error_message' => null,
        ];
    }

    /**
     * Indicate that the image is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'generated_image_path' => null,
        ]);
    }

    /**
     * Indicate that the image is processing.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'generated_image_path' => null,
        ]);
    }

    /**
     * Indicate that the image is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'error_message' => null,
        ]);
    }

    /**
     * Indicate that the image has failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'generated_image_path' => null,
            'error_message' => fake()->sentence(),
        ]);
    }
}
