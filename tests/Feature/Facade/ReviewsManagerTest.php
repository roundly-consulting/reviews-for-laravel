<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\DataTransferObjects\RatingSummary;
use RoundlyConsulting\Reviews\DataTransferObjects\UpdateReviewData;
use RoundlyConsulting\Reviews\Enums\ReviewStatus;
use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Tests\Entity;

it('approves a review through the facade', function (): void {
    $review = Review::factory()->pending()->create();

    $approved = Reviews::approve($review);

    expect($approved->status)->toBe(ReviewStatus::Approved);
});

it('rejects a review through the facade', function (): void {
    $review = Review::factory()->approved()->create();

    $rejected = Reviews::reject($review, 'Spam');

    expect($rejected->status)->toBe(ReviewStatus::Rejected)
        ->and($rejected->meta?->get('rejection_reason'))->toBe('Spam');
});

it('updates a review through the facade', function (): void {
    $review = Review::factory()->approved()->create(['title' => 'Old']);

    $updated = Reviews::update($review, new UpdateReviewData(title: 'New'));

    expect($updated->title)->toBe('New');
});

it('deletes a review through the facade', function (): void {
    $review = Review::factory()->approved()->create();

    Reviews::delete($review);

    expect(Review::query()->find($review->getKey()))->toBeNull();
});

it('returns aggregate shortcuts', function (): void {
    $reviewable = Entity::create();
    Review::factory()->for($reviewable, 'reviewable')->approved()->rating(5)->create();
    Review::factory()->for($reviewable, 'reviewable')->approved()->rating(3)->create();

    expect(Reviews::averageFor($reviewable))->toBe(4.0)
        ->and(Reviews::countFor($reviewable))->toBe(2)
        ->and(Reviews::distributionFor($reviewable))->toBe([5 => 1, 3 => 1])
        ->and(Reviews::summaryFor($reviewable))->toBeInstanceOf(RatingSummary::class);
});
