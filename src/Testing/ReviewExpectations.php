<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Testing;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Reviews\Models\Review;

/**
 * Registers Pest expectation matchers for review assertions. Host applications
 * call {@see ReviewExpectations::register()} from their tests/Pest.php.
 *
 * The matchers are only defined when Pest's expectation API is available, so
 * this file never pulls Pest into the package's runtime and static analysis
 * stays clean.
 */
final class ReviewExpectations
{
    public static function register(): void
    {
        if (! function_exists('expect')) {
            return;
        }

        expect()->extend('toHaveReview', function (?Model $author = null): mixed {
            /** @var Model $reviewable */
            $reviewable = $this->value;

            $query = Review::query()
                ->topLevel()
                ->where('reviewable_type', $reviewable->getMorphClass())
                ->where('reviewable_id', $reviewable->getKey());

            if ($author !== null) {
                $query->where('author_type', $author->getMorphClass())
                    ->where('author_id', $author->getKey());
            }

            expect($query->exists())->toBeTrue();

            return $this;
        });

        expect()->extend('toHaveApprovedReview', function (): mixed {
            /** @var Model $reviewable */
            $reviewable = $this->value;

            $exists = Review::query()
                ->topLevel()
                ->approved()
                ->where('reviewable_type', $reviewable->getMorphClass())
                ->where('reviewable_id', $reviewable->getKey())
                ->exists();

            expect($exists)->toBeTrue();

            return $this;
        });
    }
}
