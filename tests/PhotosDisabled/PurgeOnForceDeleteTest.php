<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RoundlyConsulting\MediaLibrary\Models\Media;
use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\Tests\Entity;

/*
 * Force-delete is the erasure path. With the photos switch off at boot the purge hook was never
 * registered (and the listener bailed out at call time too), so photos stored while the feature
 * was on outlived their review — media rows and public files alike.
 */

it('purges photos stored while the feature was on when the review is force-deleted', function (): void {
    Storage::fake('public');

    config()->set('reviews.photos.enabled', true);
    $review = Reviews::for(Entity::create())
        ->by(Entity::create())
        ->rating(5)
        ->withPhoto(UploadedFile::fake()->image('a.jpg', 400, 300))
        ->create();
    config()->set('reviews.photos.enabled', false);

    expect(Media::query()->count())->toBe(1)
        ->and(Storage::disk('public')->allFiles())->not->toBe([]);

    $review->forceDelete();

    expect(Media::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});
