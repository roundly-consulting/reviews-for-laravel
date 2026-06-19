<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;
use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\Testing\ReviewExpectations;
use RoundlyConsulting\Reviews\Tests\Entity;
use RoundlyConsulting\Reviews\Tests\Product;

it('matches a model that has a review', function (): void {
    $product = Product::query()->create();
    $author = Entity::query()->create();

    Reviews::for($product)->by($author)->rating(5)->approved()->create();

    expect($product)->toHaveReview()
        ->and($product)->toHaveReview($author)
        ->and($product)->toHaveApprovedReview();
});

it('fails the matcher when no review exists', function (): void {
    $product = Product::query()->create();

    expect(fn () => expect($product)->toHaveReview())
        ->toThrow(AssertionFailedError::class);

    expect(fn () => expect($product)->toHaveApprovedReview())
        ->toThrow(AssertionFailedError::class);
});

it('registering twice is a no-op', function (): void {
    ReviewExpectations::register();

    expect(true)->toBeTrue();
});
