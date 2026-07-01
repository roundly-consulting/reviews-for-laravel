<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RoundlyConsulting\MediaLibrary\Models\Media;
use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\Listeners\PurgeReviewPhotos;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Tests\Entity;

beforeEach(function (): void {
    Storage::fake('public');
});

function reviewWithPhoto(): Review
{
    return Reviews::for(Entity::create())
        ->by(Entity::create())
        ->rating(5)
        ->content('great')
        ->withPhoto(UploadedFile::fake()->image('a.jpg', 400, 300))
        ->create();
}

it('clears the photos bucket on force-delete', function (): void {
    $review = reviewWithPhoto();

    expect(Media::query()->count())->toBe(1);

    $review->forceDelete();

    expect(Media::query()->count())->toBe(0);
});

it('keeps photos on soft-delete', function (): void {
    $review = reviewWithPhoto();

    Reviews::delete($review); // soft-delete

    expect($review->trashed())->toBeTrue()
        ->and(Media::query()->count())->toBe(1);
});

it('leaves media untouched on force-delete when photos are disabled', function (): void {
    $review = reviewWithPhoto();

    config()->set('reviews.photos.enabled', false);

    (new PurgeReviewPhotos)->handle($review);

    expect(Media::query()->count())->toBe(1);
});

it('keeps photos available after a restore', function (): void {
    $review = reviewWithPhoto();

    $review->delete();
    $review->restore();

    expect($review->fresh()->photoCount())->toBe(1)
        ->and(Media::query()->count())->toBe(1);
});
