<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Reviews\Actions\RejectReview;
use RoundlyConsulting\Reviews\Enums\ReviewStatus;
use RoundlyConsulting\Reviews\Events\ReviewRejected;
use RoundlyConsulting\Reviews\Models\Review;

beforeEach(function (): void {
    $this->action = app(RejectReview::class);
});

it('rejects a review and records the reason', function (): void {
    Event::fake(ReviewRejected::class);

    $review = Review::factory()->approved()->create();

    $rejected = $this->action->execute($review, 'Spam');

    expect($rejected->status)->toBe(ReviewStatus::Rejected)
        ->and($rejected->approved_at)->toBeNull()
        ->and($rejected->meta?->get('rejection_reason'))->toBe('Spam');

    Event::assertDispatched(fn (ReviewRejected $e): bool => $e->reason === 'Spam');
});

it('rejects without a reason', function (): void {
    $review = Review::factory()->pending()->create();

    $rejected = $this->action->execute($review);

    expect($rejected->status)->toBe(ReviewStatus::Rejected)
        ->and($rejected->meta)->toBeNull();
});

it('is idempotent when already rejected', function (): void {
    Event::fake(ReviewRejected::class);

    $review = Review::factory()->rejected()->create();

    $this->action->execute($review);

    Event::assertNotDispatched(ReviewRejected::class);
});
