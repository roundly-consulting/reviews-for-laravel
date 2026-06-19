<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Testing;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\Models\Review;

/**
 * Opt-in testing ergonomics for host applications. Use it from a Pest/PHPUnit
 * test case:
 *
 *     uses(RoundlyConsulting\Reviews\Testing\InteractsWithReviews::class);
 *
 * It is intentionally framework-light and pulls in no runtime dependency on
 * Pest.
 */
trait InteractsWithReviews
{
    private ?Model $actingReviewer = null;

    public function actingAsReviewer(Model $reviewer): static
    {
        $this->actingReviewer = $reviewer;

        return $this;
    }

    public function reviewAs(Model $reviewable, ?int $rating = null, ?string $content = null, ?Model $reviewer = null): Review
    {
        return Reviews::for($reviewable)
            ->by($this->reviewer($reviewer))
            ->rating($rating)
            ->content($content)
            ->create();
    }

    private function reviewer(?Model $reviewer): Model
    {
        $resolved = $reviewer ?? $this->actingReviewer;

        if ($resolved === null) {
            throw new \RuntimeException('No reviewer set. Call actingAsReviewer() first or pass one.');
        }

        return $resolved;
    }
}
