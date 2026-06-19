<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Exceptions;

final class InvalidRatingException extends ReviewException
{
    public static function outOfRange(int $given, int $min, int $max): self
    {
        return new self((string) trans('reviews::messages.rating.out_of_range', [
            'given' => $given,
            'min' => $min,
            'max' => $max,
        ]));
    }
}
