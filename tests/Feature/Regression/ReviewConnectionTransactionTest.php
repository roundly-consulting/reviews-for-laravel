<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RoundlyConsulting\MediaLibrary\Exceptions\FileUnacceptableForBucket;
use RoundlyConsulting\MediaLibrary\Models\Media;
use RoundlyConsulting\Reviews\Exceptions\InvalidReviewException;
use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\Tests\Entity;
use RoundlyConsulting\Reviews\Tests\SideReview;

/*
 * The create transaction was opened on the default connection, so a `reviews.model` on a
 * connection of its own saved its row outside any transaction: a failed photo left the review
 * behind, autocommitted, with no photos and no ReviewCreated. The transaction now runs on the
 * review model's connection, with media-library's connection nested inside when it differs.
 */

beforeEach(function (): void {
    Storage::fake('public');
    SideReview::createSideConnection();
    config()->set('reviews.model', SideReview::class);
});

afterEach(function (): void {
    SideReview::dropSideConnection();
});

it('rolls back a review on its own connection when the photos are refused', function (): void {
    config()->set('reviews.photos.max', 1);

    $levels = [];
    DB::listen(function (QueryExecuted $query) use (&$levels): void {
        if ($query->connectionName === 'side' && str_starts_with(strtolower($query->sql), 'insert into')) {
            $levels[] = $query->connection->transactionLevel();
        }
    });

    expect(fn () => Reviews::for(Entity::create())
        ->by(Entity::create())
        ->rating(5)
        ->withPhotos([
            UploadedFile::fake()->image('a.jpg', 40, 30),
            UploadedFile::fake()->image('b.jpg', 40, 30),
        ])
        ->create())->toThrow(InvalidReviewException::class);

    expect(SideReview::query()->count())->toBe(0)
        ->and($levels)->not->toBeEmpty()
        ->and(min($levels))->toBeGreaterThan(0);
});

it('rolls back the review and the earlier photo rows when a later photo fails', function (): void {
    expect(fn () => Reviews::for(Entity::create())
        ->by(Entity::create())
        ->rating(5)
        ->withPhotos([
            UploadedFile::fake()->image('a.jpg', 40, 30),
            UploadedFile::fake()->create('notes.txt', 1, 'text/plain'),
        ])
        ->create())->toThrow(FileUnacceptableForBucket::class);

    expect(SideReview::query()->count())->toBe(0)
        ->and(Media::query()->count())->toBe(0);
});
