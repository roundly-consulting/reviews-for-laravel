<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\Enums\ReviewStatus;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Tests\Entity;

it('creates a default review with a rating and pending status', function (): void {
    $review = Review::factory()->create();

    expect($review)
        ->title->not->toBeEmpty()
        ->content->not->toBeEmpty()
        ->and($review->rating)->toBeGreaterThanOrEqual(1)
        ->and($review->status)->toBe(ReviewStatus::Pending);
});

it('builds status states', function (): void {
    expect(Review::factory()->approved()->create()->status)->toBe(ReviewStatus::Approved)
        ->and(Review::factory()->approved()->create()->approved_at)->not->toBeNull()
        ->and(Review::factory()->pending()->create()->status)->toBe(ReviewStatus::Pending)
        ->and(Review::factory()->rejected()->create()->status)->toBe(ReviewStatus::Rejected);
});

it('builds rating states', function (): void {
    expect(Review::factory()->rating(2)->create()->rating)->toBe(2)
        ->and(Review::factory()->withoutRating()->create(['content' => 'x'])->rating)->toBeNull();
});

it('builds morph states', function (): void {
    $author = Entity::create();
    $reviewable = Entity::create();

    $review = Review::factory()->byAuthor($author)->forReviewable($reviewable)->create();

    expect($review->author_id)->toBe($author->getKey())
        ->and($review->reviewable_id)->toBe($reviewable->getKey());
});
