<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Tests\Entity;
use RoundlyConsulting\Reviews\Tests\Product;

it('marks a review verified through the builder', function (): void {
    $product = Product::query()->create();
    $author = Entity::query()->create();

    $review = Reviews::for($product)->by($author)->rating(5)->verified()->create();

    expect($review->verified)->toBeTrue();
});

it('defaults to unverified', function (): void {
    $product = Product::query()->create();
    $author = Entity::query()->create();

    $review = Reviews::for($product)->by($author)->rating(5)->create();

    expect($review->verified)->toBeFalse();
});

it('filters verified and unverified reviews', function (): void {
    Review::factory()->verified()->count(2)->create();
    Review::factory()->unverified()->count(3)->create();

    expect(Review::query()->verified()->count())->toBe(2)
        ->and(Review::query()->unverified()->count())->toBe(3);
});

it('toggles verification with the model helpers, through the manager', function (): void {
    $review = Review::factory()->unverified()->create();

    $review->markVerified();
    expect($review->fresh()->verified)->toBeTrue();

    $review->markUnverified();
    expect($review->fresh()->verified)->toBeFalse();
});

it('toggles verification through the facade', function (): void {
    $review = Review::factory()->unverified()->create();

    Reviews::verify($review);
    expect($review->fresh()?->verified)->toBeTrue();

    Reviews::unverify($review);
    expect($review->fresh()?->verified)->toBeFalse();
});
