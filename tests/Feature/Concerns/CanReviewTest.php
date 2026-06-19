<?php

declare(strict_types=1);

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
