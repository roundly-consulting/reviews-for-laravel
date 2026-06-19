<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\Exceptions\InvalidReviewException;
use RoundlyConsulting\Reviews\Exceptions\ReviewException;

it('builds an empty-review exception', function (): void {
    $exception = InvalidReviewException::empty();

    expect($exception)
        ->toBeInstanceOf(ReviewException::class)
        ->and($exception->getMessage())->toBe('A review must have either a rating or content.');
});

it('builds a duplicate-review exception', function (): void {
    $exception = InvalidReviewException::duplicate();

    expect($exception)
        ->toBeInstanceOf(ReviewException::class)
        ->and($exception->getMessage())->toBe('This author has already reviewed this subject.');
});
