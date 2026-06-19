<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Reviews\Actions\RespondToReview;
use RoundlyConsulting\Reviews\Events\ReviewResponded;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Tests\Entity;
use RoundlyConsulting\Reviews\Tests\Product;

it('creates an approved response tied to the parent and subject', function (): void {
    Event::fake([ReviewResponded::class]);

    $product = Product::query()->create();
    $review = Review::factory()->approved()->forReviewable($product)->create();
    $owner = Entity::query()->create();

    $response = app(RespondToReview::class)->execute($review, $owner, 'Thanks for the feedback!');

    expect($response->parent_id)->toBe($review->id)
        ->and($response->isResponse())->toBeTrue()
        ->and($response->rating)->toBeNull()
        ->and($response->isApproved())->toBeTrue()
        ->and($response->reviewable_id)->toBe($product->getKey())
        ->and($review->responses()->count())->toBe(1);

    Event::assertDispatched(ReviewResponded::class);
});

it('excludes responses from aggregates', function (): void {
    $product = Product::query()->create();
    $review = Review::factory()->approved()->rating(4)->forReviewable($product)->create();
    $owner = Entity::query()->create();

    $review->respond($owner, 'Glad you enjoyed it.');

    expect($product->approvedReviewsCount())->toBe(1)
        ->and($product->averageRating())->toBe(4.0)
        ->and($product->reviewsCount())->toBe(1);
});
