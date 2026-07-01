<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RoundlyConsulting\MediaLibrary\Contracts\HasMedia;
use RoundlyConsulting\MediaLibrary\Exceptions\FileUnacceptableForBucket;
use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Tests\Entity;

beforeEach(function (): void {
    Storage::fake('public');
});

function photoReview(): Review
{
    return Reviews::for(Entity::create())->by(Entity::create())->rating(5)->content('great')->create();
}

it('makes a review a media owner', function (): void {
    expect(photoReview())->toBeInstanceOf(HasMedia::class);
});

it('declares a public photos bucket from config', function (): void {
    $review = photoReview();

    $bucket = $review->resolveMediaBucket('photos');

    expect($bucket)->not->toBeNull()
        ->and($bucket->name)->toBe('photos')
        ->and($bucket->getVisibility())->toBe('public')
        ->and($bucket->getAcceptedMimeTypes())->toContain('image/jpeg')
        ->and($bucket->getResponsiveWidths())->toBe([320, 640, 1024]);
});

it('reports an empty gallery on a fresh review', function (): void {
    $review = photoReview();

    expect($review->hasPhotos())->toBeFalse()
        ->and($review->photos())->toBeEmpty()
        ->and($review->photoCount())->toBe(0)
        ->and($review->firstPhotoUrl())->toBe('')
        ->and($review->photoUrls())->toBe([])
        ->and($review->responsivePhotos())->toBe([]);
});

it('accepts an image into the photos bucket', function (): void {
    $review = photoReview();

    $media = $review->addMedia(UploadedFile::fake()->image('a.jpg', 400, 300))->toMediaBucket('photos');

    expect($review->hasPhotos())->toBeTrue()
        ->and($review->photoCount())->toBe(1)
        ->and($media->bucket_name)->toBe('photos')
        ->and($media->isPublic())->toBeTrue()
        ->and($review->firstPhotoUrl())->not->toBe('')
        ->and($review->photoUrls())->toHaveCount(1);
});

it('rejects a non-image mime type', function (): void {
    $review = photoReview();

    $review->addMedia(UploadedFile::fake()->create('doc.pdf', 12, 'application/pdf'))->toMediaBucket('photos');
})->throws(FileUnacceptableForBucket::class);

it('exposes responsive markup with a srcset', function (): void {
    $review = photoReview();

    $review->addMedia(UploadedFile::fake()->image('a.jpg', 800, 600))->toMediaBucket('photos');

    $markup = $review->responsivePhotos(['class' => 'photo']);

    expect($markup)->toHaveCount(1)
        ->and($markup[0])->toContain('srcset')
        ->and($markup[0])->toContain('class="photo"');
});

it('is inert when photos are disabled', function (): void {
    config()->set('reviews.photos.enabled', false);

    $review = photoReview();

    expect($review->resolveMediaBucket('photos'))->toBeNull()
        ->and($review->hasPhotos())->toBeFalse()
        ->and($review->photos())->toBeEmpty()
        ->and($review->firstPhotoUrl())->toBe('')
        ->and($review->photoUrls())->toBe([]);
});

it('mints a temporary URL for the first photo', function (): void {
    $review = photoReview();

    expect($review->firstPhotoTemporaryUrl())->toBe('');

    $review->addMedia(UploadedFile::fake()->image('a.jpg', 400, 300))->toMediaBucket('photos');

    expect($review->firstPhotoTemporaryUrl())->not->toBe('');
});

it('returns an empty temporary URL when photos are disabled', function (): void {
    $review = photoReview();
    $review->addMedia(UploadedFile::fake()->image('a.jpg', 400, 300))->toMediaBucket('photos');

    config()->set('reviews.photos.enabled', false);

    expect($review->firstPhotoTemporaryUrl())->toBe('');
});

it('honours a private visibility switch', function (): void {
    config()->set('reviews.photos.visibility', 'private');

    $review = photoReview();

    $bucket = $review->resolveMediaBucket('photos');

    expect($bucket?->getVisibility())->toBe('private');
});

it('honours a custom bucket name from config', function (): void {
    config()->set('reviews.photos.bucket', 'gallery');

    $review = photoReview();

    expect($review->photosBucket())->toBe('gallery')
        ->and($review->resolveMediaBucket('gallery'))->not->toBeNull();
});
