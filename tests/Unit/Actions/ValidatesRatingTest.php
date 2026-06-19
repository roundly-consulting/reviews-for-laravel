<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\Actions\ValidatesRating;
use RoundlyConsulting\Reviews\Exceptions\InvalidRatingException;

beforeEach(function (): void {
    $this->validate = new ValidatesRating;
});

it('passes a null rating', function (): void {
    $this->validate->execute(null);
})->throwsNoExceptions();

it('passes a rating within range', function (): void {
    $this->validate->execute(3);
})->throwsNoExceptions();

it('rejects a rating below the minimum', function (): void {
    $this->validate->execute(0);
})->throws(InvalidRatingException::class);

it('rejects a rating above the maximum', function (): void {
    $this->validate->execute(6);
})->throws(InvalidRatingException::class);

it('respects a custom configured range', function (): void {
    config()->set('reviews.max_rating', 10);

    $this->validate->execute(8);
})->throwsNoExceptions();
