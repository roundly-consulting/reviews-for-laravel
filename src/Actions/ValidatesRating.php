<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Actions;

use RoundlyConsulting\Reviews\Exceptions\InvalidRatingException;
use RoundlyConsulting\Reviews\Support\ReviewsConfig;

/**
 * Guards a rating against the configured `reviews.min_rating` / `reviews.max_rating` range. A junk
 * or out-of-range bound throws InvalidConfigurationException rather than becoming 0.
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

        [$min, $max] = ReviewsConfig::ratingRange();

        if ($rating < $min || $rating > $max) {
            throw InvalidRatingException::outOfRange($rating, $min, $max);
        }
    }
}
