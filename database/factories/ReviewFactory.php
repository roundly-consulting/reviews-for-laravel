<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use RoundlyConsulting\Reviews\Enums\ReviewStatus;
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
            'rating' => fake()->numberBetween(1, (int) config('reviews.max_rating', 5)),
            'status' => ReviewStatus::Pending->value,
            'verified' => false,
        ];
    }

    public function verified(): self
    {
        return $this->state(fn (): array => ['verified' => true]);
    }

    public function unverified(): self
    {
        return $this->state(fn (): array => ['verified' => false]);
    }

    public function response(Review $parent): self
    {
        return $this->state(fn (): array => [
            'parent_id' => $parent->getKey(),
            'rating' => null,
            'reviewable_type' => $parent->reviewable_type,
            'reviewable_id' => $parent->reviewable_id,
        ]);
    }

    public function helpfulVotes(int $helpful, int $unhelpful = 0): self
    {
        return $this->state(fn (): array => [
            'helpful_count' => $helpful,
            'unhelpful_count' => $unhelpful,
        ]);
    }

    public function pending(): self
    {
        return $this->state(fn (): array => [
            'status' => ReviewStatus::Pending->value,
            'approved_at' => null,
        ]);
    }

    public function approved(): self
    {
        return $this->state(fn (): array => [
            'status' => ReviewStatus::Approved->value,
            'approved_at' => Date::now(),
        ]);
    }

    public function rejected(): self
    {
        return $this->state(fn (): array => [
            'status' => ReviewStatus::Rejected->value,
            'approved_at' => null,
        ]);
    }

    public function rating(int $rating): self
    {
        return $this->state(fn (): array => ['rating' => $rating]);
    }

    public function withoutRating(): self
    {
        return $this->state(fn (): array => ['rating' => null]);
    }

    public function forReviewable(Model $reviewable): self
    {
        return $this->state(fn (): array => [
            'reviewable_type' => $reviewable->getMorphClass(),
            'reviewable_id' => $reviewable->getKey(),
        ]);
    }

    public function byAuthor(Model $author): self
    {
        return $this->state(fn (): array => [
            'author_type' => $author->getMorphClass(),
            'author_id' => $author->getKey(),
        ]);
    }
}
