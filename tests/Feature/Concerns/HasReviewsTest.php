<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\DataTransferObjects\RatingSummary;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Tests\Entity;
use RoundlyConsulting\Reviews\Tests\Product;

it('exposes the reviews relation', function (): void {
    $product = Product::create();
    Review::factory()->forReviewable($product)->count(2)->create();

    expect($product->reviews)->toHaveCount(2);
});

it('scopes approved reviews', function (): void {
    $product = Product::create();
    Review::factory()->forReviewable($product)->approved()->create();
    Review::factory()->forReviewable($product)->pending()->create();

    expect($product->approvedReviews)->toHaveCount(1);
});

it('averages only approved ratings', function (): void {
    $product = Product::create();
    Review::factory()->forReviewable($product)->approved()->rating(4)->create();
    Review::factory()->forReviewable($product)->approved()->rating(2)->create();
    Review::factory()->forReviewable($product)->pending()->rating(5)->create();

    expect($product->averageRating())->toBe(3.0);
});

it('returns null average when there are no approved reviews', function (): void {
    $product = Product::create();
    Review::factory()->forReviewable($product)->pending()->rating(5)->create();

    expect($product->averageRating())->toBeNull();
});

it('counts reviews and approved reviews separately', function (): void {
    $product = Product::create();
    Review::factory()->forReviewable($product)->approved()->create();
    Review::factory()->forReviewable($product)->pending()->create();

    expect($product->reviewsCount())->toBe(2)
        ->and($product->approvedReviewsCount())->toBe(1);
});

it('builds a rating distribution of approved reviews', function (): void {
    $product = Product::create();
    Review::factory()->forReviewable($product)->approved()->rating(5)->count(3)->create();
    Review::factory()->forReviewable($product)->approved()->rating(4)->create();
    Review::factory()->forReviewable($product)->pending()->rating(1)->create();

    expect($product->ratingDistribution())->toBe([5 => 3, 4 => 1]);
});

it('excludes soft-deleted reviews from aggregates', function (): void {
    $product = Product::create();
    $review = Review::factory()->forReviewable($product)->approved()->rating(5)->create();
    Review::factory()->forReviewable($product)->approved()->rating(1)->create();

    $review->delete();

    expect($product->averageRating())->toBe(1.0)
        ->and($product->approvedReviewsCount())->toBe(1);
});

it('returns a rating summary dto', function (): void {
    $product = Product::create();
    Review::factory()->forReviewable($product)->approved()->rating(5)->create();
    Review::factory()->forReviewable($product)->approved()->rating(3)->create();

    $summary = $product->ratingSummary();

    expect($summary)->toBeInstanceOf(RatingSummary::class)
        ->and($summary->average)->toBe(4.0)
        ->and($summary->count)->toBe(2)
        ->and($summary->distribution)->toBe([5 => 1, 3 => 1]);
});

it('adds a review through the trait helper', function (): void {
    $product = Product::create();
    $author = Entity::create();

    $review = $product->addReview($author)->rating(5)->content('Great')->create();

    expect($review->reviewable->is($product))->toBeTrue()
        ->and($review->author->is($author))->toBeTrue()
        ->and($review->rating)->toBe(5);
});
