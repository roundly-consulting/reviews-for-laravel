<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RoundlyConsulting\MediaLibrary\Exceptions\InvalidVariant;
use RoundlyConsulting\MediaLibrary\Models\Media;
use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Tests\Entity;

/*
 * The photo URL readers take a variant name. The bucket's variants are its responsive ladder,
 * named `responsive-{width}` — there is no `thumb`. A declared variant that has not been
 * generated yet (queued generation, or a width added to the ladder later) serves the original
 * instead of throwing; an undeclared name stays loud, so a typo is not silently papered over.
 */

beforeEach(function (): void {
    Storage::fake('public');
});

function reviewWithOnePhoto(): Review
{
    return Reviews::for(Entity::create())
        ->by(Entity::create())
        ->rating(5)
        ->withPhoto(UploadedFile::fake()->image('a.jpg', 800, 600))
        ->create();
}

function forgetGeneratedVariants(Review $review): Media
{
    /** @var Media $media */
    $media = $review->photos()->first();
    $media->forceFill(['generated_variants' => null])->save();

    return $media->refresh();
}

it('serves a generated responsive variant by its name', function (): void {
    $review = reviewWithOnePhoto();
    $media = $review->photos()->first();

    expect($review->firstPhotoUrl('responsive-320'))->toBe($media->getUrl('responsive-320'))
        ->and($review->firstPhotoUrl('responsive-320'))->not->toBe($media->getUrl());
});

it('serves the original for a ladder width wider than the photo', function (): void {
    $review = reviewWithOnePhoto();
    $media = $review->photos()->first();

    // 800px wide: the ladder never upscales, so responsive-1024 is never generated for it.
    expect($media->hasGeneratedVariant('responsive-1024'))->toBeFalse()
        ->and($review->firstPhotoUrl('responsive-1024'))->toBe($media->getUrl());
});

it('falls back to the original while a declared variant is not generated yet', function (): void {
    $review = reviewWithOnePhoto();
    $media = forgetGeneratedVariants($review);
    $review = $review->fresh();

    expect($review->firstPhotoUrl('responsive-320'))->toBe($media->getUrl())
        ->and($review->photoUrls('responsive-640'))->toBe([$media->getUrl()])
        ->and($review->resolvePhotoUrl($media, 'responsive-1024'))->toBe($media->getUrl())
        ->and($review->firstPhotoTemporaryUrl('responsive-320'))->toBe($media->getTemporaryUrl(now()->addMinutes(5)));
});

it('throws for a variant name the bucket never declared', function (): void {
    reviewWithOnePhoto()->firstPhotoUrl('thumb');
})->throws(InvalidVariant::class);
