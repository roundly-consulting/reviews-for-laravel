<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Tests\Member;
use RoundlyConsulting\Reviews\Tests\Product;

/*
 * `parent_id` was `nullOnDelete()`, and nothing in the package removed a review's owner responses
 * when the review itself was force-deleted. The database nulled their parent instead, which made
 * each response a top-level, approved review: the subject still counted a review, and the owner
 * suddenly "had reviewed" it.
 */

/**
 * @return array{Product, Member, Review, Review, Review}
 */
function reviewWithResponses(): array
{
    $product = Product::create();
    $owner = Member::create();

    $review = Reviews::for($product)->by(Member::create())->rating(5)->approved()->create();
    $live = Reviews::respond($review, $owner, 'Thanks!');
    $trashed = Reviews::respond($review, $owner, 'An older answer');
    Reviews::delete($trashed);

    return [$product, $owner, $review, $live, $trashed];
}

it('force-deletes a review together with its live and trashed responses', function (): void {
    [$product, $owner, $review] = reviewWithResponses();

    $review->forceDelete();

    expect(Review::withTrashed()->count())->toBe(0)
        ->and(Reviews::for($product)->count())->toBe(0)
        ->and($owner->hasReviewed($product))->toBeFalse();
});

it('force-deletes each response through Eloquent, so its own hooks run', function (): void {
    [, , $review, $live, $trashed] = reviewWithResponses();

    $forceDeleted = [];
    Review::forceDeleted(function (Review $deleted) use (&$forceDeleted): void {
        $forceDeleted[] = $deleted->getKey();
    });

    $review->forceDelete();

    // Both responses first (in whatever order the engine returns them), then the review.
    expect($forceDeleted)->toHaveCount(3)
        ->and($forceDeleted[2])->toBe($review->getKey())
        ->and(array_slice($forceDeleted, 0, 2))->toEqualCanonicalizing([$live->getKey(), $trashed->getKey()]);
});
