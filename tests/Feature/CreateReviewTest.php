<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Reviews\Actions\CreateReview;
use RoundlyConsulting\Reviews\DataTransferObjects\CreateReviewData;
use RoundlyConsulting\Reviews\Enums\ReviewStatus;
use RoundlyConsulting\Reviews\Events\ReviewCreated;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Tests\CustomReview;
use RoundlyConsulting\Reviews\Tests\Entity;

it('creates a review and dispatches the event', function (): void {
    Event::fake(ReviewCreated::class);

    $action = app(CreateReview::class);

    $review = $action->execute(new CreateReviewData(
        author: $author = Entity::create(),
        reviewable: $reviewable = Entity::create(),
        content: 'Hello, this is my review.',
        title: 'Very nice place',
        rating: 5,
        meta: collect(['staff' => 'nice']),
    ));

    Event::assertDispatched(fn (ReviewCreated $e): bool => $e->review->is($review));

    expect($review)
        ->toBeInstanceOf(Review::class)
        ->title->toBe('Very nice place')
        ->content->toBe('Hello, this is my review.')
        ->rating->toBe(5)
        ->and($review->status)->toBe(ReviewStatus::Pending);

    expect($review->meta?->all())->toBe(['staff' => 'nice']);

    $this->assertDatabaseHas('reviews', [
        'author_id' => $author->getKey(),
        'author_type' => $author->getMorphClass(),
        'reviewable_id' => $reviewable->getKey(),
        'reviewable_type' => $reviewable->getMorphClass(),
        'title' => 'Very nice place',
        'content' => 'Hello, this is my review.',
        'rating' => 5,
        'status' => ReviewStatus::Pending->value,
        'meta' => $this->castAsJson(['staff' => 'nice']),
    ]);
});

it('creates a review without an optional title or meta', function (): void {
    $action = app(CreateReview::class);

    $review = $action->execute(new CreateReviewData(
        author: Entity::create(),
        reviewable: Entity::create(),
        content: 'Just the content.',
    ));

    expect($review)
        ->title->toBeNull()
        ->meta->toBeNull()
        ->rating->toBeNull()
        ->content->toBe('Just the content.');
});

it('uses the model configured via reviews.model', function (): void {
    config()->set('reviews.model', CustomReview::class);

    $action = app(CreateReview::class);

    $review = $action->execute(new CreateReviewData(
        author: Entity::create(),
        reviewable: Entity::create(),
        content: 'Custom model review.',
    ));

    expect($review)->toBeInstanceOf(CustomReview::class);
});
