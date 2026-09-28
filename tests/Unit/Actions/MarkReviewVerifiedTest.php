<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Reviews\Actions\MarkReviewVerified;
use RoundlyConsulting\Reviews\Events\ReviewVerified;
use RoundlyConsulting\Reviews\Models\Review;

beforeEach(function (): void {
    Event::fake([ReviewVerified::class]);
    $this->action = app(MarkReviewVerified::class);
});

it('verifies a review and dispatches ReviewVerified', function (): void {
    $review = Review::factory()->unverified()->create();

    $result = $this->action->execute($review);

    expect($result)->toBe($review)
        ->and($review->fresh()?->verified)->toBeTrue();

    Event::assertDispatched(ReviewVerified::class, fn (ReviewVerified $event): bool => $event->review->is($review) && $event->verified);
});

it('unverifies a review and dispatches ReviewVerified with the new state', function (): void {
    $review = Review::factory()->verified()->create();

    $this->action->execute($review, false);

    expect($review->fresh()?->verified)->toBeFalse();

    Event::assertDispatched(ReviewVerified::class, fn (ReviewVerified $event): bool => ! $event->verified);
});

it('is idempotent and silent when the review is already in that state', function (): void {
    $verified = Review::factory()->verified()->create();
    $unverified = Review::factory()->unverified()->create();

    $this->action->execute($verified, true);
    $this->action->execute($unverified, false);

    Event::assertNotDispatched(ReviewVerified::class);
});
