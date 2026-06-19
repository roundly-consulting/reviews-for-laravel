<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Observers\ReviewAggregateObserver;
use RoundlyConsulting\Reviews\Tests\Catalog;
use RoundlyConsulting\Reviews\Tests\Entity;
use RoundlyConsulting\Reviews\Tests\Product;

beforeEach(function (): void {
    config()->set('reviews.cache_aggregates', true);

    // Re-register the observer now that caching is on (the provider booted with
    // the default-off config).
    Review::observe(ReviewAggregateObserver::class);
});

it('keeps the cached counters in sync as approved reviews change', function (): void {
    $catalog = Catalog::query()->create();
    $author = Entity::query()->create();

    Reviews::for($catalog)->by($author)->rating(4)->approved()->create();

    $catalog->refresh();
    expect($catalog->reviews_count)->toBe(1)
        ->and((float) $catalog->reviews_avg)->toBe(4.0);

    $second = Reviews::for($catalog)->by(Entity::query()->create())->rating(2)->approved()->create();

    $catalog->refresh();
    expect($catalog->reviews_count)->toBe(2)
        ->and((float) $catalog->reviews_avg)->toBe(3.0);

    Reviews::delete($second);

    $catalog->refresh();
    expect($catalog->reviews_count)->toBe(1)
        ->and((float) $catalog->reviews_avg)->toBe(4.0);
});

it('does not count pending or response rows in the cache', function (): void {
    $catalog = Catalog::query()->create();
    $author = Entity::query()->create();

    $approved = Reviews::for($catalog)->by($author)->rating(5)->approved()->create();
    Reviews::for($catalog)->by(Entity::query()->create())->rating(1)->create(); // pending
    $approved->respond(Entity::query()->create(), 'Thanks');

    $catalog->refresh();
    expect($catalog->reviews_count)->toBe(1)
        ->and((float) $catalog->reviews_avg)->toBe(5.0);
});

it('rebuilds the cache from scratch via the recount command', function (): void {
    $catalog = Catalog::query()->create();

    Review::factory()->approved()->rating(4)->forReviewable($catalog)->create();
    Review::factory()->approved()->rating(2)->forReviewable($catalog)->create();

    // Simulate stale counters that drifted out of sync.
    $catalog->forceFill(['reviews_count' => 0, 'reviews_avg' => null])->saveQuietly();
    expect($catalog->fresh()->reviews_count)->toBe(0);

    $this->artisan('reviews:recount', ['model' => Catalog::class])
        ->assertSuccessful();

    $catalog->refresh();
    expect($catalog->reviews_count)->toBe(2)
        ->and((float) $catalog->reviews_avg)->toBe(3.0);
});

it('fails the recount command for a non-reviewable model', function (): void {
    $this->artisan('reviews:recount', ['model' => Entity::class])
        ->assertFailed();

    $this->artisan('reviews:recount', ['model' => 'NotAClass'])
        ->assertFailed();
});

it('ignores reviewables that do not opt into caching', function (): void {
    $product = Product::query()->create();

    // Product uses HasReviews but not MaintainsReviewAggregates: the observer
    // must no-op without error.
    Reviews::for($product)->by(Entity::query()->create())->rating(5)->approved()->create();

    expect($product->approvedReviewsCount())->toBe(1);
});

it('ignores reviews without a reviewable', function (): void {
    $review = Review::factory()->approved()->create(); // no reviewable attached

    expect($review->fresh())->not->toBeNull();
});

it('resyncs the cache when a soft-deleted review is restored', function (): void {
    $catalog = Catalog::query()->create();
    $review = Reviews::for($catalog)->by(Entity::query()->create())->rating(4)->approved()->create();

    Reviews::delete($review);
    expect($catalog->fresh()->reviews_count)->toBe(0);

    $review->restore();
    expect($catalog->fresh()->reviews_count)->toBe(1);
});
