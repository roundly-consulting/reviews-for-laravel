<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Reviews;
use RoundlyConsulting\Reviews\Tests\Member;
use RoundlyConsulting\Reviews\Tests\Product;
use RoundlyConsulting\Reviews\Tests\TenantReview;

/**
 * The shipped Pest matchers are part of the package's public API, so they have to honour the
 * `reviews.model` seam like every other call site. They hard-coded `Review::query()`, so a host
 * that pointed the config at its own model had them read the packaged table and fail on a review
 * that plainly existed.
 */
beforeEach(function (): void {
    TenantReview::createTable();

    config()->set('reviews.model', TenantReview::class);
});

it('finds a review written through the configured model', function (): void {
    $product = Product::create();
    $author = Member::create();

    $review = $product->addReview($author)->rating(4)->content('Solid.')->create();

    app(Reviews::class)->approve($review);

    expect(Review::query()->count())->toBe(0)
        ->and($product)->toHaveReview()
        ->and($product)->toHaveReview($author)
        ->and($product)->toHaveApprovedReview();
});
