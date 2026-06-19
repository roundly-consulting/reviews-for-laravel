<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Reviews\Actions\ApproveReview;
use RoundlyConsulting\Reviews\Enums\ReviewStatus;
use RoundlyConsulting\Reviews\Events\ReviewApproved;
use RoundlyConsulting\Reviews\Models\Review;

beforeEach(function (): void {
    $this->action = app(ApproveReview::class);
});

it('approves a pending review and stamps the time', function (): void {
    Event::fake(ReviewApproved::class);

    $review = Review::factory()->pending()->create();

    $approved = $this->action->execute($review);

    expect($approved->status)->toBe(ReviewStatus::Approved)
        ->and($approved->approved_at)->not->toBeNull();

    Event::assertDispatched(fn (ReviewApproved $e): bool => $e->review->is($approved));
});

it('is idempotent when already approved', function (): void {
    Event::fake(ReviewApproved::class);

    $review = Review::factory()->approved()->create();

    $this->action->execute($review);

    Event::assertNotDispatched(ReviewApproved::class);
});
