<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Reviews\Actions\DeleteReview;
use RoundlyConsulting\Reviews\Events\ReviewDeleted;
use RoundlyConsulting\Reviews\Models\Review;

it('soft-deletes a review and dispatches the event', function (): void {
    Event::fake(ReviewDeleted::class);

    $review = Review::factory()->approved()->create();

    app(DeleteReview::class)->execute($review);

    expect(Review::query()->find($review->getKey()))->toBeNull()
        ->and(Review::withTrashed()->find($review->getKey()))->not->toBeNull();

    Event::assertDispatched(fn (ReviewDeleted $e): bool => $e->review->is($review));
});
