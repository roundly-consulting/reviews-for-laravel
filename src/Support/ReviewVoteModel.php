<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Support;

use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;
use RoundlyConsulting\Reviews\Models\ReviewVote;

/**
 * The single seam through which the package resolves the configured `reviews.vote_model`.
 *
 * Narrows the toolkit's `class-string<Model>` to `class-string<ReviewVote>`; a configured class
 * that is a model but not a {@see ReviewVote} falls back to the packaged model (the package's
 * vote actions, relations and tallies are all typed against it), and a non-model value throws.
 */
final class ReviewVoteModel
{
    /** @return class-string<ReviewVote> */
    public static function class(): string
    {
        $class = ModelResolver::for('reviews.vote_model', ReviewVote::class);

        return is_a($class, ReviewVote::class, true) ? $class : ReviewVote::class;
    }

    /** @return Builder<ReviewVote> */
    public static function query(): Builder
    {
        return self::class()::query();
    }
}
