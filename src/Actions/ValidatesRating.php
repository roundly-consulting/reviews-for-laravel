<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Actions;

use RoundlyConsulting\Reviews\Exceptions\InvalidRatingException;

/**
 * Guards a rating against the configured `reviews.min_rating` / `reviews.max_rating` range.
 *
 * @internal building block of CreateReview / UpdateReview.
 */
final readonly class ValidatesRating
{
    public function execute(?int $rating): void
    {
        if ($rating === null) {
            return;
        }

        $min = (int) config('reviews.min_rating', 1);
        $max = (int) config('reviews.max_rating', 5);

        if ($rating < $min || $rating > $max) {
            throw InvalidRatingException::outOfRange($rating, $min, $max);
        }
    }
}
