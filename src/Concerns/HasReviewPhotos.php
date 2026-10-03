<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Concerns;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoundlyConsulting\MediaLibrary\Concerns\InteractsWithMedia;
use RoundlyConsulting\MediaLibrary\Contracts\HasMedia;
use RoundlyConsulting\MediaLibrary\Models\Media;
use RoundlyConsulting\MediaLibrary\Variants\ResponsiveImageGenerator;
use RoundlyConsulting\MediaLibrary\Variants\VariantResolver;
use RoundlyConsulting\PackageToolkit\Support\Config;
use RoundlyConsulting\Reviews\Support\ReviewsConfig;

/**
 * First-class review photos for the bundled Review model, built on
 * roundly-consulting/media-library-for-laravel.
 *
 * Declares the review's public `photos` bucket (a responsive image gallery) on top of
 * media-library's `InteractsWithMedia` seam and adds review-specific readers. The whole
 * feature is gated by `reviews.photos.enabled`: when disabled the bucket is never declared
 * and the model behaves as if it carries no media.
 *
 * With `reviews.photos.visibility = private` every URL surface below resolves to short-lived
 * signed URLs (never a public one), and the photos are stored on `reviews.photos.private_disk`
 * unless `reviews.photos.disk` names a disk explicitly.
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

        $accepted = ReviewsConfig::acceptedMimeTypes();

        if ($accepted !== []) {
            $bucket->acceptsMimeTypes($accepted);
        }

        $maxFileSize = ReviewsConfig::photoMaxFileSize();

        if ($maxFileSize > 0) {
            $bucket->maxFileSize($maxFileSize);
        }

        $disk = ReviewsConfig::photoDisk();

        if ($disk !== null) {
            $bucket->useDisk($disk);
        } elseif ($this->photosVisibility() === ReviewsConfig::VISIBILITY_PRIVATE) {
            // A private photo must not land on media-library's default disk: that is the
            // web-served `public` disk, where the file is reachable under /storage without the
            // signed URL. Its variants follow it, whatever `media.variants_disk` says.
            $privateDisk = ReviewsConfig::privatePhotoDisk();

            $bucket->useDisk($privateDisk)->storingVariantsOnDisk($privateDisk);
        }

        // null lets media-library apply its configured default ladder; an explicit list overrides.
        $bucket->responsiveWidths(ReviewsConfig::responsiveWidths());
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
     * Resolved by visibility — see {@see self::resolvePhotoUrl()}.
     */
    public function firstPhotoUrl(string $variant = ''): string
    {
        if (! self::reviewPhotosEnabled()) {
            return '';
        }

        $media = $this->getFirstMedia($this->photosBucket());

        return $media === null
            ? $this->getFirstMediaUrl($this->photosBucket(), $variant)
            : $this->resolvePhotoUrl($media, $variant);
    }

    /**
     * URLs for every photo on the review (optionally a variant), in order, each resolved by
     * visibility — see {@see self::resolvePhotoUrl()}.
     *
     * @return list<string>
     */
    public function photoUrls(string $variant = ''): array
    {
        $urls = [];

        foreach ($this->photos() as $media) {
            $urls[] = $this->resolvePhotoUrl($media, $variant);
        }

        return $urls;
    }

    /**
     * The URL to serve a photo at, chosen by the photo's own visibility: a public photo gets its
     * public (CDN-rewritable) URL, a private one a short-lived signed URL (presigned on capable
     * disks, otherwise media's signed streaming route). A private photo never gets a public URL.
     *
     * The bucket's variants are its responsive ladder, named `responsive-{width}`. A declared
     * variant that has not been generated yet serves the original instead — see
     * {@see self::servablePhotoVariant()}.
     */
    public function resolvePhotoUrl(Media $media, string $variant = ''): string
    {
        $variant = $this->servablePhotoVariant($media, $variant);

        return $media->isPrivate()
            ? $media->getTemporaryUrl($this->photoUrlExpiry(), $variant)
            : $media->getUrl($variant);
    }

    /**
     * A photo's `srcset` (every generated responsive width, ascending), resolved by visibility.
     */
    public function photoSrcset(Media $media): string
    {
        if (! $media->isPrivate()) {
            return $media->srcset();
        }

        return implode(', ', array_map(
            fn (int $width): string => $this->resolvePhotoUrl($media, ResponsiveImageGenerator::variantName($width)).' '.$width.'w',
            app(ResponsiveImageGenerator::class)->generatedWidths($media),
        ));
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
            $markup[] = $media->isPrivate()
                ? $this->privateResponsivePhoto($media, $attributes)
                : $media->responsiveImage('', $attributes);
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

        return $media->getTemporaryUrl($expiry ?? $this->photoUrlExpiry(), $this->servablePhotoVariant($media, $variant));
    }

    public function photosBucket(): string
    {
        return ReviewsConfig::photoBucket();
    }

    /**
     * The variant a URL can actually point at: the requested one once generated, else the
     * original (`''`) when the bucket declares that variant — its generation is still queued, a
     * width joined the ladder after the photo was stored, or the photo is narrower than that
     * width (the ladder never upscales, so the original is the largest there is). A name the
     * bucket never declared is passed through untouched, so media-library still throws
     * `InvalidVariant` for a typo.
     */
    private function servablePhotoVariant(Media $media, string $variant): string
    {
        if ($variant === '' || $media->hasGeneratedVariant($variant)) {
            return $variant;
        }

        $owner = $media->model;

        if (! $owner instanceof HasMedia) {
            return $variant;
        }

        foreach (app(VariantResolver::class)->forOwnerBucket($owner, $media->bucket_name) as $declared) {
            if ($declared->name === $variant) {
                return '';
            }
        }

        return $variant;
    }

    /**
     * `public` or `private`; any other value throws rather than quietly publishing the photos.
     */
    private function photosVisibility(): string
    {
        return ReviewsConfig::photoVisibility();
    }

    /**
     * The same `<img>` media-library's `responsiveImage()` builds — smallest generated width as
     * `src`, every width in `srcset`, `sizes` / `alt` / `class`, the LQIP placeholder — with
     * signed URLs, since a private photo has no public one.
     *
     * @param  array<string, string>  $attributes
     */
    private function privateResponsivePhoto(Media $media, array $attributes): string
    {
        $widths = app(ResponsiveImageGenerator::class)->generatedWidths($media);

        $pairs = [
            'src' => $this->resolvePhotoUrl($media, $widths === [] ? '' : ResponsiveImageGenerator::variantName($widths[0])),
        ];

        $srcset = $this->photoSrcset($media);

        if ($srcset !== '') {
            $pairs['srcset'] = $srcset;
        }

        foreach (['sizes', 'alt', 'class'] as $name) {
            if (isset($attributes[$name])) {
                $pairs[$name] = $attributes[$name];
            }
        }

        $placeholder = $media->placeholderDataUri();

        if ($placeholder !== null) {
            $pairs['style'] = "background-size:cover;background-image:url('{$placeholder}')";
        }

        $rendered = '';

        foreach ($pairs as $name => $value) {
            $rendered .= ' '.$name.'="'.e($value).'"';
        }

        return '<img'.$rendered.'>';
    }

    private function photoUrlExpiry(): DateTimeInterface
    {
        return CarbonImmutable::now()->addMinutes(ReviewsConfig::temporaryUrlLifetime());
    }

    public static function reviewPhotosEnabled(): bool
    {
        return Config::boolean('reviews.photos.enabled', true);
    }
}
