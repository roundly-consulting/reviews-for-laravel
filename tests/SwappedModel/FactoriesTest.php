<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Models\ReviewVote;
use RoundlyConsulting\Reviews\Tests\TenantReview;
use RoundlyConsulting\Reviews\Tests\TenantVote;

/*
 * The factories hard-coded the packaged models: with `reviews.model` swapped onto its own table,
 * `TenantReview::factory()` built a packaged Review in `reviews`, and `ReviewVote::factory()`
 * made its parent there too — so the vote's id pointed into the host table at an unrelated review
 * (or at nothing). They now build the configured models.
 */

beforeEach(function (): void {
    TenantReview::resetCreationCount();
    TenantVote::resetCreationCount();
});

it('builds the configured review model from the packaged factory', function (): void {
    $packaged = Review::factory()->create();
    $host = TenantReview::factory()->create();

    expect($packaged)->toBeInstanceOf(TenantReview::class)
        ->and($host)->toBeInstanceOf(TenantReview::class)
        ->and($packaged->getTable())->toBe('tenant_reviews')
        ->and(TenantReview::creationCount())->toBe(2)
        ->and(TenantReview::query()->count())->toBe(2);
});

it('attaches a factory vote to the review its factory made', function (): void {
    $unrelated = TenantReview::factory()->create(['title' => 'Unrelated']);

    $vote = ReviewVote::factory()->create();

    expect($vote)->toBeInstanceOf(TenantVote::class)
        ->and(TenantVote::creationCount())->toBe(1)
        ->and(TenantReview::creationCount())->toBe(2)
        ->and($vote->review)->toBeInstanceOf(TenantReview::class)
        ->and($vote->review?->is($unrelated))->toBeFalse();
});
