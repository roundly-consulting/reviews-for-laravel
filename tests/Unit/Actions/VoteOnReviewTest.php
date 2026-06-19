<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Reviews\Actions\RemoveReviewVote;
use RoundlyConsulting\Reviews\Actions\VoteOnReview;
use RoundlyConsulting\Reviews\Events\ReviewVoted;
use RoundlyConsulting\Reviews\Events\ReviewVoteRemoved;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Models\ReviewVote;
use RoundlyConsulting\Reviews\Tests\Entity;

it('records a helpful vote and maintains the denormalized count', function (): void {
    Event::fake([ReviewVoted::class]);

    $review = Review::factory()->create();
    $voter = Entity::query()->create();

    $vote = app(VoteOnReview::class)->execute($review, $voter, true);

    expect($vote)->toBeInstanceOf(ReviewVote::class)
        ->and($vote->helpful)->toBeTrue()
        ->and($review->fresh()->helpful_count)->toBe(1)
        ->and($review->fresh()->unhelpful_count)->toBe(0);

    Event::assertDispatched(ReviewVoted::class);
});

it('keeps one vote per voter and flips an existing vote', function (): void {
    $review = Review::factory()->create();
    $voter = Entity::query()->create();

    app(VoteOnReview::class)->execute($review, $voter, true);
    app(VoteOnReview::class)->execute($review, $voter, false);

    expect(ReviewVote::query()->count())->toBe(1)
        ->and($review->fresh()->helpful_count)->toBe(0)
        ->and($review->fresh()->unhelpful_count)->toBe(1);
});

it('computes a net helpful score from many voters', function (): void {
    $review = Review::factory()->create();

    foreach (range(1, 3) as $ignored) {
        app(VoteOnReview::class)->execute($review, Entity::query()->create(), true);
    }
    app(VoteOnReview::class)->execute($review, Entity::query()->create(), false);

    $review->refresh();

    expect($review->helpful_count)->toBe(3)
        ->and($review->unhelpful_count)->toBe(1)
        ->and($review->helpfulScore())->toBe(2);
});

it('removes a vote and recomputes the count', function (): void {
    Event::fake([ReviewVoteRemoved::class]);

    $review = Review::factory()->create();
    $voter = Entity::query()->create();

    app(VoteOnReview::class)->execute($review, $voter, true);
    app(RemoveReviewVote::class)->execute($review, $voter);

    expect(ReviewVote::query()->count())->toBe(0)
        ->and($review->fresh()->helpful_count)->toBe(0);

    Event::assertDispatched(ReviewVoteRemoved::class);
});

it('is idempotent and silent when removing a missing vote', function (): void {
    Event::fake([ReviewVoteRemoved::class]);

    $review = Review::factory()->create();
    $voter = Entity::query()->create();

    app(RemoveReviewVote::class)->execute($review, $voter);

    Event::assertNotDispatched(ReviewVoteRemoved::class);
});
