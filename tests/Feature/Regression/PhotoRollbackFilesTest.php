<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RoundlyConsulting\MediaLibrary\Exceptions\FileUnacceptableForBucket;
use RoundlyConsulting\MediaLibrary\Models\Media;
use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Tests\Entity;

/*
 * Photos are added one at a time inside the create transaction, and media-library writes each
 * original and its sync variants before the next photo is checked. A refused later photo rolled
 * the rows back but left the earlier photo's files on disk with nothing pointing at them.
 * media-library 1.1.1 deletes the files an add wrote when the enclosing transaction rolls back.
 */

beforeEach(function (): void {
    Storage::fake('public');
});

it('leaves no files on disk when a later photo is refused', function (): void {
    expect(fn () => Reviews::for(Entity::create())
        ->by(Entity::create())
        ->rating(5)
        ->withPhotos([
            UploadedFile::fake()->image('a.jpg', 800, 600),
            UploadedFile::fake()->create('notes.txt', 1, 'text/plain'),
        ])
        ->create())->toThrow(FileUnacceptableForBucket::class);

    expect(Review::query()->count())->toBe(0)
        ->and(Media::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});
