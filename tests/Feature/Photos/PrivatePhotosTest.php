<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use RoundlyConsulting\MediaLibrary\Models\Media;
use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\Http\Resources\ReviewResource;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Tests\Entity;

/**
 * `reviews.photos.visibility = private` (a documented option). Two defects on that setting:
 *
 *  - every photo URL surface — firstPhotoUrl(), photoUrls(), responsivePhotos() and the
 *    resource's `url` / `srcset` — asked media-library for PUBLIC URLs, which it refuses for
 *    private media, so each of them threw `MediaCannotBeStreamed`;
 *  - the photos were still written to media-library's default disk — the web-served `public`
 *    disk — so the file sat under /storage for anyone with its path, signed URL or not.
 */
beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
    config()->set('reviews.photos.visibility', 'private');
    $this->freezeSecond();
});

function privatePhotoReview(?UploadedFile $photo = null): Review
{
    return Reviews::for(Entity::create())
        ->by(Entity::create())
        ->rating(5)
        ->content('great')
        ->withPhoto($photo ?? UploadedFile::fake()->image('a.jpg', 800, 600))
        ->create();
}

/** Every URL the public disk would publish for this media (original + generated variants). */
function publicPhotoUrls(Media $media): array
{
    $urls = [Storage::disk('public')->url($media->getPath())];

    foreach (array_keys($media->generated_variants ?? []) as $variant) {
        $urls[] = Storage::disk('public')->url($media->getPath((string) $variant));
    }

    return $urls;
}

it('links private photos through signed urls only', function (): void {
    $review = privatePhotoReview();
    $media = $review->photos()->sole();

    expect($media->isPrivate())->toBeTrue()
        ->and($review->firstPhotoUrl())->toBe($review->firstPhotoTemporaryUrl())
        ->and($review->photoUrls())->toBe([$review->firstPhotoTemporaryUrl()])
        ->and($review->resolvePhotoUrl($media, 'responsive-320'))->toBe($media->getTemporaryUrl(now()->addMinutes(5), 'responsive-320'))
        ->and($review->firstPhotoUrl())->not->toBeIn(publicPhotoUrls($media));
});

it('renders private responsive photos with signed urls only', function (): void {
    $review = privatePhotoReview();
    $media = $review->photos()->sole();

    $html = $review->responsivePhotos(['class' => 'photo', 'alt' => 'A photo'])[0];

    expect($html)->toStartWith('<img')
        ->and($html)->toContain('srcset=')
        ->and($html)->toContain('class="photo"')
        ->and($html)->toContain('alt="A photo"')
        ->and($html)->toContain(e($review->resolvePhotoUrl($media, 'responsive-320')))
        ->and($html)->toContain(e($review->resolvePhotoUrl($media, 'responsive-640')));

    foreach (publicPhotoUrls($media) as $publicUrl) {
        expect($html)->not->toContain($publicUrl.'"')
            ->and($html)->not->toContain($publicUrl.' ');
    }
});

it('exposes signed photo urls in the resource', function (): void {
    $review = privatePhotoReview();
    $media = $review->photos()->sole();

    $photo = (new ReviewResource($review))->toArray(Request::create('/'))['photos'][0];

    expect($photo['url'])->toBe($review->resolvePhotoUrl($media))
        ->and($photo['srcset'])->toBe(
            $review->resolvePhotoUrl($media, 'responsive-320').' 320w, '.$review->resolvePhotoUrl($media, 'responsive-640').' 640w'
        )
        ->and($photo['srcset'])->toBe($review->photoSrcset($media));
});

it('stores private photos and their variants on the private disk', function (): void {
    config()->set('media.variants_disk', 'public');

    $media = privatePhotoReview()->photos()->sole();

    expect($media->disk)->toBe('local')
        ->and($media->diskFor('responsive-320'))->toBe('local')
        ->and(Storage::disk('local')->exists($media->getPath()))->toBeTrue()
        ->and(Storage::disk('local')->exists($media->getPath('responsive-320')))->toBeTrue()
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('honours a configured private disk and an explicit disk', function (): void {
    config()->set('filesystems.disks.vault', ['driver' => 'local', 'root' => storage_path('app/vault')]);
    Storage::fake('vault');
    config()->set('reviews.photos.private_disk', 'vault');

    $vault = privatePhotoReview()->photos()->sole();

    config()->set('reviews.photos.disk', 'public');

    expect($vault->disk)->toBe('vault')
        ->and(privatePhotoReview()->photos()->sole()->disk)->toBe('public');
});

it('keeps public photos on the media default disk with public urls', function (): void {
    config()->set('reviews.photos.visibility', 'public');

    $review = privatePhotoReview();
    $media = $review->photos()->sole();

    expect($media->disk)->toBe('public')
        ->and($review->firstPhotoUrl())->toBe($media->getUrl())
        ->and($review->resolvePhotoUrl($media))->toBe($media->getUrl());
});

it('serves a private photo from the real private disk through the signed stream route', function (): void {
    // A real (not faked) local disk: no native temporary URLs, so media mints its own signed
    // streaming route. It must stream the bytes from the private disk and refuse a tampered link.
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

    $root = sys_get_temp_dir().'/reviews-private-'.bin2hex(random_bytes(4));
    config()->set('filesystems.disks.local', ['driver' => 'local', 'root' => $root]);
    Storage::forgetDisk('local');

    try {
        $review = privatePhotoReview(UploadedFile::fake()->image('a.jpg', 40, 30));
        $media = $review->photos()->sole();
        $url = $review->firstPhotoUrl();

        expect($media->disk)->toBe('local')
            ->and(is_file($root.'/'.$media->getPath()))->toBeTrue()
            ->and(Storage::disk('public')->exists($media->getPath()))->toBeFalse()
            ->and(URL::hasValidSignature(Request::create($url)))->toBeTrue();

        $response = $this->get($url);

        $response->assertOk();
        expect($response->streamedContent())->toBe(file_get_contents($root.'/'.$media->getPath()));

        $this->get($url.'0')->assertForbidden();
    } finally {
        Storage::forgetDisk('local');
        File::deleteDirectory($root);
    }
});
