<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\DataTransferObjects;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final readonly class CreateReviewData
{
    /**
     * @param  Collection<string, mixed>|null  $meta
     */
    public function __construct(
        public Model $author,
        public Model $reviewable,
        public ?string $content = null,
        public ?string $title = null,
        public ?int $rating = null,
        public ?Collection $meta = null,
        public bool $approved = false,
        public bool $verified = false,
    ) {}
}
