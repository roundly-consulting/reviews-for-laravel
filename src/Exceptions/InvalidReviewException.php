<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Exceptions;

final class InvalidReviewException extends ReviewException
{
    public static function empty(): self
    {
        return new self((string) trans('reviews::messages.review.empty'));
    }

    public static function duplicate(): self
    {
        return new self((string) trans('reviews::messages.review.duplicate'));
    }

    public static function tooManyPhotos(int $max): self
    {
        return new self("A review may have at most {$max} photo(s).");
    }

    public static function photosDisabled(): self
    {
        return new self('Review photos are disabled; enable reviews.photos.enabled to attach photos.');
    }
}
