<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Support;

use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Support\Config;

/**
 * Strict readers for the package's non-boolean settings.
 *
 * A value that is not set — absent, null, or blank like a host's `KEY=` — falls back to its
 * default (or, for an optional setting such as the photos disk, none). Anything else unusable —
 * `five` for a count, `privat` for a visibility, an array for a disk — throws
 * {@see InvalidConfigurationException} naming the key, so a typo never quietly picks a side
 * (e.g. 0 = unlimited photos, or public photos).
 *
 * @internal
 */
final class ReviewsConfig
{
    public const string VISIBILITY_PUBLIC = 'public';

    public const string VISIBILITY_PRIVATE = 'private';

    /** The rating column is an unsigned tinyint. */
    private const int RATING_CEILING = 255;

    /**
     * The inclusive rating range: `min_rating` 0–255, `max_rating` from `min_rating` to 255.
     *
     * @return array{int, int}
     */
    public static function ratingRange(): array
    {
        $min = Config::integer('reviews.min_rating', 1, 0, self::RATING_CEILING);
        $max = Config::integer('reviews.max_rating', 5, max($min, 1), self::RATING_CEILING);

        return [$min, $max];
    }

    /** Per-review photo limit; 0 means unlimited. */
    public static function photoLimit(): int
    {
        return Config::integer('reviews.photos.max', 5, 0);
    }

    /** Largest accepted photo upload in bytes; 0 means no per-photo cap. */
    public static function photoMaxFileSize(): int
    {
        return Config::integer('reviews.photos.max_file_size', 5 * 1024 * 1024, 0);
    }

    /** `public` or `private`. */
    public static function photoVisibility(): string
    {
        return Config::oneOf(
            'reviews.photos.visibility',
            [self::VISIBILITY_PUBLIC, self::VISIBILITY_PRIVATE],
            self::VISIBILITY_PUBLIC,
        );
    }

    public static function photoBucket(): string
    {
        return self::string('reviews.photos.bucket', 'photos');
    }

    /** The explicit photos disk, or null to choose one by visibility. */
    public static function photoDisk(): ?string
    {
        return self::unlessBlank(config('reviews.photos.disk')) === null ? null : self::string('reviews.photos.disk', '');
    }

    public static function privatePhotoDisk(): string
    {
        return self::string('reviews.photos.private_disk', 'local');
    }

    /**
     * The mime types the photos bucket accepts; an empty list accepts any type.
     *
     * @return list<string>
     */
    public static function acceptedMimeTypes(): array
    {
        $types = self::unlessBlank(config('reviews.photos.accepted_mime_types'))
            ?? ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

        return self::stringList('reviews.photos.accepted_mime_types', $types);
    }

    /**
     * The responsive width ladder, or null for media-library's default ladder.
     *
     * @return list<int>|null
     */
    public static function responsiveWidths(): ?array
    {
        $key = 'reviews.photos.responsive_widths';
        $widths = self::unlessBlank(config($key));

        if ($widths === null) {
            return null;
        }

        if (! is_array($widths) || ! array_is_list($widths)) {
            throw self::notAList($key, 'positive integers', $widths);
        }

        $clean = [];

        foreach ($widths as $width) {
            // Validated under the setting's own key, so the message names it.
            $clean[] = Config::for([$key => $width])->integer($key, 0, 1);
        }

        return array_values(array_unique($clean));
    }

    /**
     * The moderation blocklist.
     *
     * @return list<string>
     */
    public static function bannedWords(): array
    {
        return self::stringList('reviews.moderation.banned_words', self::unlessBlank(config('reviews.moderation.banned_words')) ?? []);
    }

    /** Lifetime, in minutes, of a private photo's signed URL (media-library's setting). */
    public static function temporaryUrlLifetime(): int
    {
        return Config::integer('media.temporary_url_default_lifetime', 5, 1);
    }

    private static function string(string $key, string $default): string
    {
        $value = self::unlessBlank(config($key));

        if ($value === null) {
            return $default;
        }

        if (! is_string($value)) {
            throw InvalidConfigurationException::notAString($key, $value);
        }

        return $value;
    }

    /**
     * A raw config value, with a blank string (`''` or whitespace — a host's `KEY=`) read as
     * null: not set, exactly like an absent key.
     */
    private static function unlessBlank(mixed $value): mixed
    {
        return is_string($value) && trim($value) === '' ? null : $value;
    }

    /**
     * @return list<string>
     */
    private static function stringList(string $key, mixed $values): array
    {
        if (! is_array($values) || ! array_is_list($values)) {
            throw self::notAList($key, 'non-empty strings', $values);
        }

        $strings = [];

        foreach ($values as $value) {
            if (! is_string($value) || trim($value) === '') {
                throw self::notAList($key, 'non-empty strings', $value);
            }

            $strings[] = $value;
        }

        return $strings;
    }

    private static function notAList(string $key, string $of, mixed $value): InvalidConfigurationException
    {
        $given = match (true) {
            $value === '' => "''",
            is_string($value) => $value,
            is_int($value), is_float($value), is_bool($value) => var_export($value, true),
            default => get_debug_type($value),
        };

        return new InvalidConfigurationException("Configuration value [{$key}] must be a list of {$of}, [{$given}] given.");
    }
}
