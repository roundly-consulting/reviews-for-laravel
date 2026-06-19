<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Tests\Voter;

it('lets a voter cast and detect a vote', function (): void {
    $review = Review::factory()->create();
    $voter = Voter::query()->create();

    expect($voter->hasVotedOn($review))->toBeFalse();

    $voter->voteOn($review, true);

    expect($voter->hasVotedOn($review))->toBeTrue()
        ->and($voter->votedHelpfulOn($review))->toBeTrue()
        ->and($review->fresh()->helpful_count)->toBe(1);
});

it('lets a voter flip and withdraw a vote', function (): void {
    $review = Review::factory()->create();
    $voter = Voter::query()->create();

    $voter->voteOn($review, true);
    $voter->voteOn($review, false);

    expect($voter->votedHelpfulOn($review))->toBeFalse()
        ->and($review->fresh()->unhelpful_count)->toBe(1);

    $voter->removeVoteFrom($review);

    expect($voter->hasVotedOn($review))->toBeFalse();
});

it('orders reviews by net helpful score', function (): void {
    $low = Review::factory()->helpfulVotes(1, 0)->create();
    $high = Review::factory()->helpfulVotes(10, 1)->create();
    $mid = Review::factory()->helpfulVotes(5, 0)->create();

    $ordered = Review::query()->mostHelpful()->pluck('id')->all();

    expect($ordered)->toBe([$high->id, $mid->id, $low->id]);
});
