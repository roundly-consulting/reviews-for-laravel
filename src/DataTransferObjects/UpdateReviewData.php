<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\DataTransferObjects;

use Illuminate\Support\Collection;

/**
 * Carries an edit to an existing review. Every field is optional; a null value
 * means "leave unchanged" so callers only supply what they want to alter.
 */
final readonly class UpdateReviewData
{
    /**
     * @param  Collection<string, mixed>|null  $meta
     */
    public function __construct(
        public ?string $title = null,
        public ?string $content = null,
        public ?int $rating = null,
        public ?Collection $meta = null,
        public ?bool $verified = null,
    ) {}
}
