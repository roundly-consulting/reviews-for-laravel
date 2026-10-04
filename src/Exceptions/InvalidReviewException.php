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
        return new self(trans_choice('reviews::messages.photos.too_many', $max, ['max' => $max]));
    }

    public static function photosDisabled(): self
    {
        return new self((string) trans('reviews::messages.photos.disabled'));
    }
}
