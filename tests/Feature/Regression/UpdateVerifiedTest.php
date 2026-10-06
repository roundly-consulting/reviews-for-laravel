<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\AssertionFailedError;
use RoundlyConsulting\Reviews\DataTransferObjects\UpdateReviewData;
use RoundlyConsulting\Reviews\Events\ReviewVerified;
use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\Models\Review;

/*
 * `UpdateReviewData::$verified` is a documented way to change the flag, yet only verify() /
 * unverify() announced it: a "verified buyer" listener — and the fake's assertReviewVerified() —
 * missed every change made through update(). A real change now fires ReviewVerified with the new
 * state; an update that leaves the flag as it was fires nothing.
 */

it('fires ReviewVerified when an update really changes the flag', function (): void {
    $review = Review::factory()->create();

    Event::fake([ReviewVerified::class]);

    Reviews::update($review, new UpdateReviewData(verified: true));
    Reviews::update($review, new UpdateReviewData(verified: true));

    Event::assertDispatchedTimes(ReviewVerified::class, 1);
    Event::assertDispatched(ReviewVerified::class, fn (ReviewVerified $event): bool => $event->review->is($review) && $event->verified);

    Reviews::update($review, new UpdateReviewData(verified: false));

    Event::assertDispatchedTimes(ReviewVerified::class, 2);
    Event::assertDispatched(ReviewVerified::class, fn (ReviewVerified $event): bool => ! $event->verified);
});

it('fires nothing for an update that leaves the flag alone', function (): void {
    $review = Review::factory()->verified()->create();

    Event::fake([ReviewVerified::class]);

    Reviews::update($review, new UpdateReviewData(title: 'Edited'));
    Reviews::update($review, new UpdateReviewData(verified: true));

    Event::assertNotDispatched(ReviewVerified::class);
});

it('records a verified flag changed through update under the fake', function (): void {
    $fake = Reviews::fake();
    $review = Review::factory()->create();

    Reviews::update($review, new UpdateReviewData(verified: true));

    $fake->assertReviewVerified(fn (Review $verified): bool => $verified->is($review));
    $fake->assertNothingUnverified();

    Reviews::update($review, new UpdateReviewData(verified: false));

    $fake->assertReviewUnverified(fn (Review $unverified): bool => $unverified->is($review));
    $fake->assertReviewUpdated();
});

it('records no verification for an update that leaves the flag alone under the fake', function (): void {
    $fake = Reviews::fake();
    $review = Review::factory()->verified()->create();

    Reviews::update($review, new UpdateReviewData(verified: true));

    $fake->assertNothingVerified();
    $fake->assertNothingUnverified();

    expect(fn () => $fake->assertReviewVerified())->toThrow(AssertionFailedError::class);
});
