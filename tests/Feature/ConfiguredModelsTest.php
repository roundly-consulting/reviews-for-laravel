<?php

declare(strict_types=1);

use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Models\ReviewVote;
use RoundlyConsulting\Reviews\ReviewsManager;
use RoundlyConsulting\Reviews\Support\ReviewModel;
use RoundlyConsulting\Reviews\Support\ReviewVoteModel;
use RoundlyConsulting\Reviews\Tests\Member;
use RoundlyConsulting\Reviews\Tests\Product;
use RoundlyConsulting\Reviews\Tests\TenantReview;

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

    app(ReviewsManager::class)->approve($review);

    expect($product->approvedReviewsCount())->toBe(1)
        ->and($product->averageRating())->toBe(5.0)
        ->and($product->ratingDistribution())->toBe([5 => 1])
        ->and($author->hasReviewed($product))->toBeTrue();
});

it('responds through the configured model', function (): void {
    $product = Product::create();
    $author = Member::create();

    $review = $product->addReview($author)->rating(3)->content('Fine.')->create();

    $response = $review->respond($author, 'Thanks for the feedback.');

    expect($response)->toBeInstanceOf(TenantReview::class)
        ->and($response->parent_id)->toBe($review->getKey());
});

/*
 * The VOTING half of this case used to live here and has moved to tests/SwappedModel/ —
 * this note is the finding, not a relocation.
 *
 * It voted on a review of the body-time-swapped model and passed. It could only ever pass:
 * the swap lands AFTER the package's migrations have run, so `review_votes.review_id` still
 * constrains onto `reviews` while the review itself is a row in `tenant_reviews`. The vote
 * referenced a parent that, by construction, did not exist in the parent table — and the
 * suite reported green because this package's hand-written connection config omitted
 * `foreign_key_constraints`, leaving SQLite's `PRAGMA foreign_keys` OFF. Adopting
 * PackageTestCase turns it on and the case reds instantly with `FOREIGN KEY constraint
 * failed`.
 *
 * So it was not merely a swap test that could not see the bug it was named for (the shape
 * the fleet already knew about). It was a test asserting a state **no host can reach**,
 * held up by a missing pragma. The real thing it claimed to prove — that a host's votes work
 * against a swapped review model — needs the swap in place before the migrations run, which
 * is what SwappedModelTestCase gives it, and it is proven there against an engine that
 * enforces the constraint.
 */

it('resolves the packaged models by default', function (): void {
    config()->set('reviews.model', Review::class);

    expect(ReviewModel::class())->toBe(Review::class)
        ->and(ReviewVoteModel::class())->toBe(ReviewVote::class);
});

it('refuses a foreign model instead of falling back to the packaged one', function (): void {
    // The toolkit refuses any class that is not the packaged model or a subclass of it.
    config()->set('reviews.model', Product::class);
    config()->set('reviews.vote_model', Product::class);

    expect(fn (): string => ReviewModel::class())->toThrow(
        InvalidConfigurationException::class,
        'Configuration value [reviews.model] must be a class-string of ['.Review::class.'], ['.Product::class.'] given.',
    );
    expect(fn (): string => ReviewVoteModel::class())->toThrow(
        InvalidConfigurationException::class,
        'Configuration value [reviews.vote_model] must be a class-string of ['.ReviewVote::class.'], ['.Product::class.'] given.',
    );
});

it('throws when the configured model is not a model at all', function (): void {
    config()->set('reviews.model', 'NotAClass');

    ReviewModel::class();
})->throws(InvalidConfigurationException::class);
