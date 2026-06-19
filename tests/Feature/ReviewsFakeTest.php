<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;
use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Testing\ReviewsFake;
use RoundlyConsulting\Reviews\Tests\Entity;
use RoundlyConsulting\Reviews\Tests\Product;

it('swaps in a recording fake', function (): void {
    $fake = Reviews::fake();

    expect($fake)->toBeInstanceOf(ReviewsFake::class)
        ->and(app(RoundlyConsulting\Reviews\Reviews::class))->toBe($fake);
});

it('records created reviews', function (): void {
    $fake = Reviews::fake();

    Reviews::for(Product::query()->create())->by(Entity::query()->create())->rating(5)->create();

    $fake->assertReviewCreated();
    $fake->assertReviewCreated(fn (Review $review): bool => $review->rating === 5);
});

it('asserts nothing reviewed', function (): void {
    $fake = Reviews::fake();

    $fake->assertNothingReviewed();
});

it('records approvals and rejections', function (): void {
    $fake = Reviews::fake();

    $review = Review::factory()->create();
    Reviews::approve($review);
    $fake->assertReviewApproved();

    $rejected = Review::factory()->create();
    Reviews::reject($rejected, 'spam');
    $fake->assertReviewRejected(fn (Review $r): bool => $r->is($rejected));
});

it('records responses and votes', function (): void {
    $fake = Reviews::fake();

    $review = Review::factory()->approved()->create();
    $response = Reviews::respond($review, Entity::query()->create(), 'Thanks');
    $fake->assertReviewResponded();
    $fake->assertReviewResponded(fn (Review $r): bool => $r->is($response));

    Reviews::vote($review, Entity::query()->create(), true);
    $fake->assertReviewVoted();
    $fake->assertReviewVoted(fn (Review $r): bool => $r->is($review));
});

it('matches approvals by callback', function (): void {
    $fake = Reviews::fake();

    $review = Review::factory()->create();
    Reviews::approve($review);

    $fake->assertReviewApproved(fn (Review $r): bool => $r->is($review));
});

it('fails the various assertions when nothing matched', function (): void {
    $fake = Reviews::fake();

    expect(fn () => $fake->assertReviewCreated())->toThrow(AssertionFailedError::class);
    expect(fn () => $fake->assertReviewCreated(fn (Review $r): bool => false))->toThrow(AssertionFailedError::class);
    expect(fn () => $fake->assertReviewApproved())->toThrow(AssertionFailedError::class);
    expect(fn () => $fake->assertReviewApproved(fn (Review $r): bool => false))->toThrow(AssertionFailedError::class);
    expect(fn () => $fake->assertReviewRejected())->toThrow(AssertionFailedError::class);
    expect(fn () => $fake->assertReviewRejected(fn (Review $r): bool => false))->toThrow(AssertionFailedError::class);
    expect(fn () => $fake->assertReviewResponded())->toThrow(AssertionFailedError::class);
    expect(fn () => $fake->assertReviewResponded(fn (Review $r): bool => false))->toThrow(AssertionFailedError::class);
    expect(fn () => $fake->assertReviewVoted())->toThrow(AssertionFailedError::class);
    expect(fn () => $fake->assertReviewVoted(fn (Review $r): bool => false))->toThrow(AssertionFailedError::class);
});
