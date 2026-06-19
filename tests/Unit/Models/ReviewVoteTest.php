<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Models\ReviewVote;
use RoundlyConsulting\Reviews\Tests\Entity;

it('casts helpful to a boolean', function (): void {
    $vote = ReviewVote::factory()->create();

    expect($vote->helpful)->toBeBool();
});

it('relates to its review and voter', function (): void {
    $review = Review::factory()->create();
    $voter = Entity::query()->create();

    $vote = ReviewVote::factory()->forReview($review)->byVoter($voter)->create();

    expect($vote->review->is($review))->toBeTrue()
        ->and($vote->voter->is($voter))->toBeTrue();
});

it('exposes helpful and unhelpful factory states', function (): void {
    expect(ReviewVote::factory()->helpful()->create()->helpful)->toBeTrue()
        ->and(ReviewVote::factory()->unhelpful()->create()->helpful)->toBeFalse();
});

it('exposes the votes relation from the review', function (): void {
    $review = Review::factory()->create();
    ReviewVote::factory()->forReview($review)->count(2)->create();

    expect($review->votes()->count())->toBe(2);
});
