<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Models\ReviewVote;

/** @extends Factory<ReviewVote> */
final class ReviewVoteFactory extends Factory
{
    protected $model = ReviewVote::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'review_id' => Review::factory(),
            'helpful' => true,
        ];
    }

    public function helpful(): self
    {
        return $this->state(fn (): array => ['helpful' => true]);
    }

    public function unhelpful(): self
    {
        return $this->state(fn (): array => ['helpful' => false]);
    }

    public function forReview(Review $review): self
    {
        return $this->state(fn (): array => ['review_id' => $review->getKey()]);
    }

    public function byVoter(Model $voter): self
    {
        return $this->state(fn (): array => [
            'voter_type' => $voter->getMorphClass(),
            'voter_id' => $voter->getKey(),
        ]);
    }
}
