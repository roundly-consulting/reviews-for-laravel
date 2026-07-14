<?php

declare(strict_types=1);

use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Models\ReviewVote;
use RoundlyConsulting\Reviews\Reviews;
use RoundlyConsulting\Reviews\Support\ReviewModel;
use RoundlyConsulting\Reviews\Support\ReviewVoteModel;
use RoundlyConsulting\Reviews\Tests\Member;
use RoundlyConsulting\Reviews\Tests\Product;
use RoundlyConsulting\Reviews\Tests\TenantReview;
use RoundlyConsulting\Reviews\Tests\Voter;

/**
 * `reviews.model` / `reviews.vote_model` are a documented swap seam. A host that points them at
 * its own model must have it honoured EVERYWHERE — including the shipped Pest expectations, which
 * hard-coded the packaged model and therefore queried the wrong table.
 */
beforeEach(function (): void {
    TenantReview::createTable();

    config()->set('reviews.model', TenantReview::class);
});

it('writes, reads and aggregates through the configured model', function (): void {
    $product = Product::create();
    $author = Member::create();

    $review = $product->addReview($author)->rating(5)->title('Great')->content('Loved it.')->create();

    expect($review)->toBeInstanceOf(TenantReview::class)
        ->and($review->getTable())->toBe('tenant_reviews')
        // Nothing landed in the packaged table.
        ->and(Review::query()->count())->toBe(0);

    app(Reviews::class)->approve($review);

    expect($product->approvedReviewsCount())->toBe(1)
        ->and($product->averageRating())->toBe(5.0)
        ->and($product->ratingDistribution())->toBe([5 => 1])
        ->and($author->hasReviewed($product))->toBeTrue();
});

it('responds and votes through the configured models', function (): void {
    $product = Product::create();
    $author = Member::create();
    $voter = Voter::create();

    $review = $product->addReview($author)->rating(3)->content('Fine.')->create();

    $response = $review->respond($author, 'Thanks for the feedback.');

    expect($response)->toBeInstanceOf(TenantReview::class)
        ->and($response->parent_id)->toBe($review->getKey());

    $vote = app(Reviews::class)->vote($review, $voter);

    expect($vote)->toBeInstanceOf(ReviewVote::class)
        ->and($vote->review)->toBeInstanceOf(TenantReview::class)
        ->and($review->fresh()?->helpful_count)->toBe(1)
        ->and($voter->hasVotedOn($review))->toBeTrue();

    app(Reviews::class)->removeVote($review, $voter);

    expect($review->fresh()?->helpful_count)->toBe(0);
});

it('resolves the packaged models by default', function (): void {
    config()->set('reviews.model', Review::class);

    expect(ReviewModel::class())->toBe(Review::class)
        ->and(ReviewVoteModel::class())->toBe(ReviewVote::class);
});

it('falls back to the packaged model when the configured class is not a review', function (): void {
    config()->set('reviews.model', Product::class);
    config()->set('reviews.vote_model', Product::class);

    expect(ReviewModel::class())->toBe(Review::class)
        ->and(ReviewVoteModel::class())->toBe(ReviewVote::class);
});

it('throws when the configured model is not a model at all', function (): void {
    config()->set('reviews.model', 'NotAClass');

    ReviewModel::class();
})->throws(InvalidConfigurationException::class);
