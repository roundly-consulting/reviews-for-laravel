<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use RoundlyConsulting\MediaLibrary\Exceptions\DraftMediaNotFound;
use RoundlyConsulting\MediaLibrary\Facades\MediaLibrary;
use RoundlyConsulting\Reviews\Events\ReviewCreated;
use RoundlyConsulting\Reviews\Exceptions\InvalidReviewException;
use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Tests\Entity;

beforeEach(function (): void {
    Storage::fake('public');
});

function photoDraftToken(string $name = 'draft.jpg'): string
{
    return (string) MediaLibrary::draft(UploadedFile::fake()->image($name, 400, 300))
        ->toBucket('photos')
        ->draft_token;
}

it('creates a review with a single uploaded photo', function (): void {
    $review = Reviews::for(Entity::create())
        ->by(Entity::create())
        ->rating(5)
        ->content('great')
        ->withPhoto(UploadedFile::fake()->image('a.jpg', 400, 300))
        ->create();

    expect($review->photoCount())->toBe(1)
        ->and($review->photos()->first()->bucket_name)->toBe('photos');
});

it('creates a review with multiple photos', function (): void {
    $review = Reviews::for(Entity::create())
        ->by(Entity::create())
        ->rating(4)
        ->content('nice')
        ->withPhotos([
            UploadedFile::fake()->image('a.jpg', 400, 300),
            UploadedFile::fake()->image('b.png', 400, 300),
        ])
        ->create();

    expect($review->photoCount())->toBe(2);
});

it('attaches a draft photo by token', function (): void {
    $token = photoDraftToken();

    $review = Reviews::for(Entity::create())
        ->by(Entity::create())
        ->rating(5)
        ->content('drafted')
        ->withDraftPhoto($token)
        ->create();

    expect($review->photoCount())->toBe(1)
        ->and($review->photos()->first()->draft_token)->toBeNull();
});

it('attaches a photo from a disk path', function (): void {
    Storage::disk('public')->putFileAs('incoming', UploadedFile::fake()->image('a.jpg', 400, 300), 'a.jpg');

    $review = Reviews::for(Entity::create())
        ->by(Entity::create())
        ->rating(5)
        ->content('from disk')
        ->withPhotoFromDisk('incoming/a.jpg', 'public')
        ->create();

    expect($review->photoCount())->toBe(1);
});

it('exposes photos to ReviewCreated listeners', function (): void {
    $seen = null;
    Event::listen(ReviewCreated::class, function (ReviewCreated $event) use (&$seen): void {
        $seen = $event->review->photoCount();
    });

    Reviews::for(Entity::create())
        ->by(Entity::create())
        ->rating(5)
        ->content('great')
        ->withPhoto(UploadedFile::fake()->image('a.jpg', 400, 300))
        ->create();

    expect($seen)->toBe(1);
});

it('throws when the per-review photo limit is exceeded', function (): void {
    config()->set('reviews.photos.max', 2);

    Reviews::for(Entity::create())
        ->by(Entity::create())
        ->rating(5)
        ->content('too many')
        ->withPhotos([
            UploadedFile::fake()->image('a.jpg', 400, 300),
            UploadedFile::fake()->image('b.jpg', 400, 300),
            UploadedFile::fake()->image('c.jpg', 400, 300),
        ])
        ->create();
})->throws(InvalidReviewException::class, 'at most 2');

it('allows unlimited photos when max is zero', function (): void {
    config()->set('reviews.photos.max', 0);

    $review = Reviews::for(Entity::create())
        ->by(Entity::create())
        ->rating(5)
        ->content('lots')
        ->withPhotos([
            UploadedFile::fake()->image('a.jpg', 400, 300),
            UploadedFile::fake()->image('b.jpg', 400, 300),
            UploadedFile::fake()->image('c.jpg', 400, 300),
        ])
        ->create();

    expect($review->photoCount())->toBe(3);
});

it('throws immediately when attaching a photo while photos are disabled', function (): void {
    config()->set('reviews.photos.enabled', false);

    Reviews::for(Entity::create())
        ->by(Entity::create())
        ->rating(5)
        ->content('nope')
        ->withPhoto(UploadedFile::fake()->image('a.jpg', 400, 300));
})->throws(InvalidReviewException::class, 'disabled');

it('rolls the review back when a photo attach fails', function (): void {
    try {
        Reviews::for(Entity::create())
            ->by(Entity::create())
            ->rating(5)
            ->content('doomed')
            ->withDraftPhoto('does-not-exist')
            ->create();

        test()->fail('Expected DraftMediaNotFound to be thrown.');
    } catch (DraftMediaNotFound) {
        expect(Review::query()->count())->toBe(0);
    }
});
