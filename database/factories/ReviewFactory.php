<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Reviews\Models\Review;

/** @extends Factory<Review> */
final class ReviewFactory extends Factory
{
    protected $model = Review::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'title' => fake()->words(asText: true),
            'content' => fake()->sentence(),
        ];
    }
}
