<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use RoundlyConsulting\Reviews\Enums\ReviewStatus;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Tests\Entity;

it('has a relationship to the reviewable', function (): void {
    $reviewable = Entity::create();

    $review = Review::factory()->for($reviewable, 'reviewable')->create();

    expect($review)
        ->reviewable_type->toBe(Entity::class)
        ->reviewable_id->toBe($reviewable->id)
        ->reviewable->is($reviewable)->toBeTrue();
});

it('has a relationship to the author', function (): void {
    $author = Entity::create();

    $review = Review::factory()->for($author, 'author')->create();

    expect($review)
        ->author_type->toBe(Entity::class)
        ->author_id->toBe($author->id)
        ->author->is($author)->toBeTrue();
});

it('casts meta to a collection', function (): void {
    $review = Review::factory()->create(['meta' => collect(['rating' => 5])]);

    expect($review->fresh()->meta)
        ->toBeInstanceOf(Collection::class)
        ->all()->toBe(['rating' => 5]);
});

it('casts status to the enum', function (): void {
    $review = Review::factory()->approved()->create();

    expect($review->fresh()->status)->toBe(ReviewStatus::Approved);
});

it('casts approved_at to an immutable date', function (): void {
    $review = Review::factory()->approved()->create();

    expect($review->fresh()->approved_at)->toBeInstanceOf(CarbonImmutable::class);
});

it('exposes status helper methods', function (): void {
    expect(Review::factory()->pending()->create()->isPending())->toBeTrue()
        ->and(Review::factory()->approved()->create()->isApproved())->toBeTrue()
        ->and(Review::factory()->rejected()->create()->isRejected())->toBeTrue();
});
