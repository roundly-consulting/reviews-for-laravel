<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Tests\Entity;
use RoundlyConsulting\Reviews\Tests\Member;

it('exposes authored reviews', function (): void {
    $member = Member::create();
    Review::factory()->byAuthor($member)->count(2)->create();

    expect($member->reviewsAuthored)->toHaveCount(2);
});

it('knows whether it has reviewed a subject', function (): void {
    $member = Member::create();
    $reviewable = Entity::create();
    $other = Entity::create();

    Review::factory()->byAuthor($member)->forReviewable($reviewable)->create();

    expect($member->hasReviewed($reviewable))->toBeTrue()
        ->and($member->hasReviewed($other))->toBeFalse();
});

it('returns the review for a subject', function (): void {
    $member = Member::create();
    $reviewable = Entity::create();

    $review = Review::factory()->byAuthor($member)->forReviewable($reviewable)->create();

    expect($member->reviewFor($reviewable)?->is($review))->toBeTrue()
        ->and($member->reviewFor(Entity::create()))->toBeNull();
});

it('does not count an owner response as having reviewed the subject', function (): void {
    $seller = Member::create();
    $product = Entity::create();
    $review = Review::factory()->approved()->forReviewable($product)->create();

    Reviews::respond($review, $seller, 'Thanks for the feedback!');

    expect($seller->hasReviewed($product))->toBeFalse()
        ->and($seller->reviewFor($product))->toBeNull();
});

it('returns its own review, not a response it wrote on the same subject', function (): void {
    $seller = Member::create();
    $product = Entity::create();

    Reviews::respond(Review::factory()->approved()->forReviewable($product)->create(), $seller, 'Thanks!');
    $own = Review::factory()->byAuthor($seller)->forReviewable($product)->create();

    expect($seller->hasReviewed($product))->toBeTrue()
        ->and($seller->reviewFor($product)?->is($own))->toBeTrue();
});
