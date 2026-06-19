<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Tests\Entity;
use RoundlyConsulting\Reviews\Tests\Product;
use RoundlyConsulting\Reviews\Tests\ReviewerHarness;

it('reviews as a remembered reviewer', function (): void {
    $harness = new ReviewerHarness;
    $reviewer = Entity::query()->create();

    $review = $harness->actingAsReviewer($reviewer)->reviewAs(Product::query()->create(), 4, 'Nice');

    expect($review)->toBeInstanceOf(Review::class)
        ->and($review->rating)->toBe(4)
        ->and($review->author_id)->toBe($reviewer->getKey());
});

it('throws when no reviewer is set', function (): void {
    $harness = new ReviewerHarness;

    expect(fn () => $harness->reviewAs(Product::query()->create(), 3, 'x'))
        ->toThrow(RuntimeException::class);
});
