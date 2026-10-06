<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Actions;

use Illuminate\Support\Str;
use RoundlyConsulting\Reviews\Exceptions\InvalidReviewException;

/**
 * Guards the "a rating or content" rule: a review without a rating needs content that is more than
 * whitespace. Only checked here — the text itself is stored exactly as given, never trimmed.
 *
 * @internal building block of CreateReview / UpdateReview.
 */
final readonly class ValidatesReviewContent
{
    public function execute(?int $rating, ?string $content): void
    {
        if ($rating === null && Str::trim((string) $content) === '') {
            throw InvalidReviewException::empty();
        }
    }
}
