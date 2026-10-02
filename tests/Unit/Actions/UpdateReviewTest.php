<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Reviews\Actions\UpdateReview;
use RoundlyConsulting\Reviews\DataTransferObjects\UpdateReviewData;
use RoundlyConsulting\Reviews\Enums\ReviewStatus;
use RoundlyConsulting\Reviews\Events\ReviewUpdated;
use RoundlyConsulting\Reviews\Exceptions\InvalidRatingException;
use RoundlyConsulting\Reviews\Models\Review;

beforeEach(function (): void {
    $this->action = app(UpdateReview::class);
});

it('updates only the provided fields', function (): void {
    Event::fake(ReviewUpdated::class);

    $review = Review::factory()->approved()->create([
        'title' => 'Old',
        'content' => 'Old content',
        'rating' => 3,
    ]);

    $updated = $this->action->execute($review, new UpdateReviewData(title: 'New'));

    expect($updated->title)->toBe('New')
        ->and($updated->content)->toBe('Old content')
        ->and($updated->rating)->toBe(3);

    Event::assertDispatched(ReviewUpdated::class);
});

it('re-moderates when content changes and the flag is on', function (): void {
    $review = Review::factory()->approved()->create();

    $updated = $this->action->execute($review, new UpdateReviewData(content: 'Edited'));

    expect($updated->status)->toBe(ReviewStatus::Pending)
        ->and($updated->approved_at)->toBeNull();
});

it('re-moderates when the rating changes', function (): void {
    $review = Review::factory()->approved()->create(['rating' => 3]);

    $updated = $this->action->execute($review, new UpdateReviewData(rating: 5));

    expect($updated->rating)->toBe(5)
        ->and($updated->status)->toBe(ReviewStatus::Pending);
});

it('keeps the status when re-moderation is disabled', function (): void {
    config()->set('reviews.reset_status_on_edit', false);

    $review = Review::factory()->approved()->create();

    $updated = $this->action->execute($review, new UpdateReviewData(content: 'Edited'));

    expect($updated->status)->toBe(ReviewStatus::Approved);
});

it('re-moderates a title edit too', function (): void {
    // The title is shown and screened (the word-list moderator reads it) like the content.
    $review = Review::factory()->approved()->create();

    $updated = $this->action->execute($review, new UpdateReviewData(title: 'Just the title'));

    expect($updated->status)->toBe(ReviewStatus::Pending);
});

it('does not re-moderate a meta-only edit', function (): void {
    $review = Review::factory()->approved()->create();

    $updated = $this->action->execute($review, new UpdateReviewData(meta: collect(['source' => 'import'])));

    expect($updated->status)->toBe(ReviewStatus::Approved);
});

it('updates the meta collection', function (): void {
    $review = Review::factory()->approved()->create();

    $updated = $this->action->execute($review, new UpdateReviewData(meta: collect(['edited' => true])));

    expect($updated->meta?->get('edited'))->toBeTrue();
});

it('rejects an out-of-range rating on update', function (): void {
    $review = Review::factory()->approved()->create();

    $this->action->execute($review, new UpdateReviewData(rating: 99));
})->throws(InvalidRatingException::class);
