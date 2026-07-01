<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use RoundlyConsulting\MediaLibrary\Jobs\GenerateVariantsJob;
use RoundlyConsulting\MediaLibrary\Models\Media;
use RoundlyConsulting\Reviews\Events\ReviewApproved;

/**
 * On ReviewApproved, (re)warm the review's photo variants so responsive thumbnails are hot the
 * moment the review goes live: one queued {@see GenerateVariantsJob} per photo. No-op when photos
 * are disabled, warming is turned off, or the review carries no photos.
 */
final class WarmReviewPhotoVariants implements ShouldQueue
{
    public function handle(ReviewApproved $event): void
    {
        if (! (bool) config('reviews.photos.enabled', true)) {
            return;
        }

        if (! (bool) config('reviews.photos.warm_on_approval', true)) {
            return;
        }

        foreach ($event->review->photos() as $media) {
            $this->warm($media);
        }
    }

    private function warm(Media $media): void
    {
        $variantNames = array_map(
            static fn (object $variant): string => $variant->name,
            $media->resolveVariants(),
        );

        if ($variantNames === []) {
            return;
        }

        GenerateVariantsJob::dispatch((int) $media->getKey(), $variantNames);
    }
}
