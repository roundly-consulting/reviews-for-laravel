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
     * Absent config resolves the packaged model; anything else must be that model or a subclass
     * of it, or the toolkit's ModelResolver throws InvalidConfigurationException naming the key
     * — a foreign class is never silently replaced.
     *
     * @return class-string<Review>
     */
    public static function class(): string
    {
        return ModelResolver::for('reviews.model', Review::class);
    }

    public static function new(): Review
    {
        $class = self::class();

        return new $class;
    }

    /**
     * The table the configured review model lives on.
     *
     * Migrations constrain against this rather than a literal `reviews`, so a host that swaps in a
     * model on its own table gets a foreign key that its rows can actually satisfy.
     */
    public static function table(): string
    {
        return self::new()->getTable();
    }

    /** @return Builder<Review> */
    public static function query(): Builder
    {
        return self::class()::query();
    }
}
