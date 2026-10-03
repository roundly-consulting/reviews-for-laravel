<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Support;

use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;
use RoundlyConsulting\Reviews\Models\ReviewVote;

/**
 * The single seam through which the package resolves the configured `reviews.vote_model`.
 *
 * Absent config resolves the packaged model; anything else must be that model or a subclass of
 * it, or the toolkit's ModelResolver throws InvalidConfigurationException naming the key — a
 * foreign class is never silently replaced.
 */
final class ReviewVoteModel
{
    /** @return class-string<ReviewVote> */
    public static function class(): string
    {
        return ModelResolver::for('reviews.vote_model', ReviewVote::class);
    }

    /** @return Builder<ReviewVote> */
    public static function query(): Builder
    {
        return self::class()::query();
    }
}
