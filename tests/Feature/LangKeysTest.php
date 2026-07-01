<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\Exceptions\InvalidRatingException;
use RoundlyConsulting\Reviews\Exceptions\InvalidReviewException;

it('keeps the rating and review lang keys after dropping status', function (): void {
    // The status sub-array moved to the enums trait; these keys stay because the
    // exceptions and the word-list moderator still consume them.
    expect(trans('reviews::messages.rating.out_of_range'))
        ->not->toBe('reviews::messages.rating.out_of_range')
        ->and(trans('reviews::messages.review.empty'))->toBe('A review must have either a rating or content.')
        ->and(trans('reviews::messages.review.duplicate'))->toBe('This author has already reviewed this subject.')
        ->and(trans('reviews::messages.review.banned_word'))->toBe('This review contains language that is not allowed.');
});

it('no longer resolves the removed status lang sub-array', function (): void {
    expect(trans('reviews::messages.status.pending'))->toBe('reviews::messages.status.pending');
});

it('still surfaces translated messages through the exceptions', function (): void {
    expect(InvalidReviewException::empty()->getMessage())
        ->toBe('A review must have either a rating or content.')
        ->and(InvalidRatingException::outOfRange(9, 1, 5)->getMessage())
        ->toContain('between 1 and 5');
});
