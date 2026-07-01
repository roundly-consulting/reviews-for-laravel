<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Concerns;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoundlyConsulting\MediaLibrary\Concerns\InteractsWithMedia;
use RoundlyConsulting\MediaLibrary\Models\Media;

/**
 * First-class review photos for the bundled Review model, built on
 * roundly-consulting/media-library-for-laravel.
 *
 * Declares the review's public `photos` bucket (a responsive image gallery) on top of
 * media-library's `InteractsWithMedia` seam and adds review-specific readers. The whole
 * feature is gated by `reviews.photos.enabled`: when disabled the bucket is never declared
 * and the model behaves as if it carries no media.
 *
 * @mixin Model
 */
trait HasReviewPhotos
{
    use InteractsWithMedia;

    public function registerMediaBuckets(): void
    {
        if (! self::reviewPhotosEnabled()) {
            return;
        }

        $bucket = $this->addMediaBucket($this->photosBucket())
            ->withVisibility($this->photosVisibility());

        $accepted = config('reviews.photos.accepted_mime_types');

        if (is_array($accepted) && $accepted !== []) {
            $bucket->acceptsMimeTypes($this->stringList($accepted));
        }

        $maxFileSize = config('reviews.photos.max_file_size');

        if (is_int($maxFileSize) && $maxFileSize > 0) {
            $bucket->maxFileSize($maxFileSize);
        }

        $disk = config('reviews.photos.disk');

        if (is_string($disk) && $disk !== '') {
            $bucket->useDisk($disk);
        }

        $widths = config('reviews.photos.responsive_widths');

        // null lets media-library apply its configured default ladder; an explicit list overrides.
        $bucket->responsiveWidths(is_array($widths) ? $this->normalizeWidths($widths) : null);
    }

    /**
     * Every photo on this review, in bucket (ordered) sequence.
     *
     * @return Collection<int, Media>
     */
    public function photos(): Collection
    {
        if (! self::reviewPhotosEnabled()) {
            return new Collection;
        }

        return $this->getMedia($this->photosBucket());
    }

    public function hasPhotos(): bool
    {
        return self::reviewPhotosEnabled() && $this->hasMedia($this->photosBucket());
    }

    public function photoCount(): int
    {
        return $this->photos()->count();
    }

    /**
     * The URL of the review's first photo (optionally a variant), or the bucket fallback / '' .
     */
    public function firstPhotoUrl(string $variant = ''): string
    {
        if (! self::reviewPhotosEnabled()) {
            return '';
        }

        return $this->getFirstMediaUrl($this->photosBucket(), $variant);
    }

    /**
     * URLs for every photo on the review (optionally a variant), in order.
     *
     * @return list<string>
     */
    public function photoUrls(string $variant = ''): array
    {
        $urls = [];

        foreach ($this->photos() as $media) {
            $urls[] = $media->getUrl($variant);
        }

        return $urls;
    }

    /**
     * Responsive `<img>` markup (with `srcset`) for every photo on the review.
     *
     * @param  array<string, string>  $attributes
     * @return list<string>
     */
    public function responsivePhotos(array $attributes = []): array
    {
        $markup = [];

        foreach ($this->photos() as $media) {
            $markup[] = $media->responsiveImage('', $attributes);
        }

        return $markup;
    }

    /**
     * A short-lived signed URL for the review's first photo — useful when the bucket is private.
     */
    public function firstPhotoTemporaryUrl(string $variant = '', ?DateTimeInterface $expiry = null): string
    {
        if (! self::reviewPhotosEnabled()) {
            return '';
        }

        $media = $this->getFirstMedia($this->photosBucket());

        if ($media === null) {
            return '';
        }

        return $media->getTemporaryUrl($expiry ?? CarbonImmutable::now()->addMinutes(5), $variant);
    }

    public function photosBucket(): string
    {
        $bucket = config('reviews.photos.bucket', 'photos');

        return is_string($bucket) && $bucket !== '' ? $bucket : 'photos';
    }

    private function photosVisibility(): string
    {
        $visibility = config('reviews.photos.visibility', 'public');

        return $visibility === 'private' ? 'private' : 'public';
    }

    public static function reviewPhotosEnabled(): bool
    {
        return (bool) config('reviews.photos.enabled', true);
    }

    /**
     * @param  array<int|string, mixed>  $values
     * @return list<string>
     */
    private function stringList(array $values): array
    {
        $strings = [];

        foreach ($values as $value) {
            if (is_string($value) && $value !== '') {
                $strings[] = $value;
            }
        }

        return $strings;
    }

    /**
     * @param  array<int|string, mixed>  $widths
     * @return list<int>
     */
    private function normalizeWidths(array $widths): array
    {
        $clean = [];

        foreach ($widths as $width) {
            if (is_int($width) && $width > 0) {
                $clean[] = $width;
            }
        }

        return array_values(array_unique($clean));
    }
}
