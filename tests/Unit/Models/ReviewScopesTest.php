<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Tests\Entity;

it('filters by status', function (): void {
    Review::factory()->approved()->create();
    Review::factory()->pending()->create();
    Review::factory()->rejected()->create();

    expect(Review::query()->approved()->count())->toBe(1)
        ->and(Review::query()->pending()->count())->toBe(1)
        ->and(Review::query()->rejected()->count())->toBe(1);
});

it('filters rated reviews', function (): void {
    Review::factory()->rating(4)->create();
    Review::factory()->withoutRating()->create(['content' => 'text only']);

    expect(Review::query()->rated()->count())->toBe(1);
});

it('filters by an exact rating', function (): void {
    Review::factory()->rating(5)->create();
    Review::factory()->rating(3)->create();

    expect(Review::query()->withRatingOf(5)->count())->toBe(1);
});

it('filters by a minimum rating', function (): void {
    Review::factory()->rating(2)->create();
    Review::factory()->rating(4)->create();
    Review::factory()->rating(5)->create();

    expect(Review::query()->minRating(4)->count())->toBe(2);
});

it('filters by author and reviewable', function (): void {
    $author = Entity::create();
    $reviewable = Entity::create();

    Review::factory()->byAuthor($author)->forReviewable($reviewable)->create();
    Review::factory()->create();

    expect(Review::query()->authoredBy($author)->count())->toBe(1)
        ->and(Review::query()->for($reviewable)->count())->toBe(1);
});

it('orders the latest reviews first', function (): void {
    $older = Review::factory()->approved()->create(['approved_at' => now()->subDay()]);
    $newer = Review::factory()->approved()->create(['approved_at' => now()]);

    $ordered = Review::query()->latestFirst()->get();

    expect($ordered->first()->is($newer))->toBeTrue()
        ->and($ordered->last()->is($older))->toBeTrue();
});
