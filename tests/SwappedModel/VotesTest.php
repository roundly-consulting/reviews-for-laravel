<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Reviews\Reviews;
use RoundlyConsulting\Reviews\Support\ReviewModel;
use RoundlyConsulting\Reviews\Tests\Member;
use RoundlyConsulting\Reviews\Tests\Product;
use RoundlyConsulting\Reviews\Tests\TenantReview;
use RoundlyConsulting\Reviews\Tests\Voter;

/**
 * `reviews.model` is a documented swap seam, so the votes table's foreign key must target the
 * CONFIGURED model's table. Hard-coding `reviews` there means a swapped model's votes can never
 * satisfy the constraint on any engine that enforces foreign keys.
 */
it('constrains review_votes.review_id to the configured model table', function (): void {
    expect(ReviewModel::table())->toBe('tenant_reviews');

    $foreignKey = collect(Schema::getForeignKeys('review_votes'))
        ->firstWhere(fn (array $key): bool => $key['columns'] === ['review_id']);

    expect($foreignKey)->not->toBeNull()
        ->and($foreignKey['foreign_table'])->toBe('tenant_reviews')
        ->and($foreignKey['foreign_columns'])->toBe(['id']);
});

it('stores a vote on a review of the configured model', function (): void {
    $review = Product::create()->addReview(Member::create())->rating(4)->content('Good.')->create();

    expect($review)->toBeInstanceOf(TenantReview::class)
        ->and($review->getTable())->toBe('tenant_reviews');

    $vote = app(Reviews::class)->vote($review, Voter::create());

    expect($vote->review_id)->toBe($review->getKey())
        ->and($review->fresh()?->helpful_count)->toBe(1);
});

it('cascades votes away when the configured model row is deleted', function (): void {
    $review = Product::create()->addReview(Member::create())->rating(2)->content('Meh.')->create();

    app(Reviews::class)->vote($review, Voter::create());

    $review->forceDelete();

    expect(DB::table('review_votes')->count())->toBe(0);
});
