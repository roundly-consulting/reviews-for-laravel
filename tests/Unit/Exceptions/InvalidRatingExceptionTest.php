<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\Exceptions\InvalidRatingException;
use RoundlyConsulting\Reviews\Exceptions\ReviewException;

it('builds an out-of-range exception with a helpful message', function (): void {
    $exception = InvalidRatingException::outOfRange(9, 1, 5);

    expect($exception)
        ->toBeInstanceOf(ReviewException::class)
        ->and($exception->getMessage())->toBe('The rating 9 must be between 1 and 5.');
});
