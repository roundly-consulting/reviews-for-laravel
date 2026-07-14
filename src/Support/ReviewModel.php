<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Support;

use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;
use RoundlyConsulting\Reviews\Models\Review;

/**
 * The single seam through which the package resolves the configured `reviews.model`.
 *
 * Every read/write of the reviews table goes through here, so a host that swaps the model gets it
 * honoured everywhere — including the aggregate observer and the photo-purge hook the provider
 * registers on the configured class, and the shipped Pest expectations. Narrows the toolkit's
 * `class-string<Model>` to `class-string<Review>`, so no call site needs an inline `@var` override.
 */
final class ReviewModel
{
    /**
     * The configured review model.
     *
     * A configured class that is a real Eloquent model but not a {@see Review} cannot serve the
     * package (every action, event, scope and observer is typed against `Review`), so it falls back
     * to the packaged model — the tolerance the hand-written `@var` overrides always had. A value
     * that is not a model class at all throws.
     *
     * @return class-string<Review>
     */
    public static function class(): string
    {
        $class = ModelResolver::for('reviews.model', Review::class);

        return is_a($class, Review::class, true) ? $class : Review::class;
    }

    public static function new(): Review
    {
        $class = self::class();

        return new $class;
    }

    /** @return Builder<Review> */
    public static function query(): Builder
    {
        return self::class()::query();
    }
}
