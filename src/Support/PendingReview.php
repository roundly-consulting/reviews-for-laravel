<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoundlyConsulting\Reviews\Actions\CreateReview;
use RoundlyConsulting\Reviews\DataTransferObjects\CreateReviewData;
use RoundlyConsulting\Reviews\Models\Review;

/**
 * Fluent builder for creating a review. Chain setters then call create().
 */
class PendingReview
{
    protected ?Model $reviewable = null;

    protected ?Model $author = null;

    protected ?int $rating = null;

    protected ?string $title = null;

    protected ?string $content = null;

    /** @var Collection<string, mixed>|null */
    protected ?Collection $meta = null;

    protected bool $approved = false;

    protected bool $verified = false;

    public function __construct(
        private readonly CreateReview $createReview = new CreateReview,
    ) {}

    public function for(Model $reviewable): self
    {
        $this->reviewable = $reviewable;

        return $this;
    }

    public function by(Model $author): self
    {
        $this->author = $author;

        return $this;
    }

    public function rating(?int $rating): self
    {
        $this->rating = $rating;

        return $this;
    }

    public function title(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function content(?string $content): self
    {
        $this->content = $content;

        return $this;
    }

    /**
     * @param  array<string, mixed>|Collection<string, mixed>|null  $meta
     */
    public function meta(array|Collection|null $meta): self
    {
        $this->meta = $meta === null ? null : collect($meta);

        return $this;
    }

    public function approved(): self
    {
        $this->approved = true;

        return $this;
    }

    public function verified(bool $verified = true): self
    {
        $this->verified = $verified;

        return $this;
    }

    public function create(): Review
    {
        return $this->createReview->execute(new CreateReviewData(
            author: $this->resolveAuthor(),
            reviewable: $this->resolveReviewable(),
            content: $this->content,
            title: $this->title,
            rating: $this->rating,
            meta: $this->meta,
            approved: $this->approved,
            verified: $this->verified,
        ));
    }

    private function resolveAuthor(): Model
    {
        if ($this->author === null) {
            throw new \LogicException('A review author is required; call by($author) before create().');
        }

        return $this->author;
    }

    private function resolveReviewable(): Model
    {
        if ($this->reviewable === null) {
            throw new \LogicException('A reviewable subject is required; call for($subject) before create().');
        }

        return $this->reviewable;
    }
}
