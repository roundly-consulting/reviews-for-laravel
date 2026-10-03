<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Reviews\Actions\ValidatesRating;
use RoundlyConsulting\Reviews\Exceptions\InvalidRatingException;
use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Moderation\WordListModerator;
use RoundlyConsulting\Reviews\Tests\Entity;

/**
 * Sweep 2 — the non-boolean settings. `(int) env()` turned `REVIEWS_PHOTOS_MAX=five` into 0,
 * which the package reads as UNLIMITED photos; a `photos.visibility` typo (`privat`) made every
 * photo PUBLIC; a junk bucket, disk, width or mime list quietly fell back. Each value now goes
 * through a strict reader: the default applies only when the key is absent, anything present
 * but unusable throws.
 */
const REVIEWS_VALUE_ENV = [
    'REVIEWS_MIN_RATING' => 'min_rating',
    'REVIEWS_MAX_RATING' => 'max_rating',
    'REVIEWS_PHOTOS_MAX' => 'photos.max',
    'REVIEWS_PHOTOS_MAX_FILE_SIZE' => 'photos.max_file_size',
];

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
});

afterEach(function (): void {
    foreach (array_keys(REVIEWS_VALUE_ENV) as $name) {
        Env::getRepository()->clear($name);
    }
});

function strictPhotoReview(): Review
{
    return Reviews::for(Entity::create())->by(Entity::create())->rating(5)->content('great')->create();
}

/**
 * @param  list<UploadedFile>  $photos
 */
function strictReviewWithPhotos(array $photos): Review
{
    return Reviews::for(Entity::create())->by(Entity::create())->rating(5)->content('great')->withPhotos($photos)->create();
}

/**
 * @return list<UploadedFile>
 */
function strictPhotos(int $count): array
{
    return array_map(
        static fn (int $i): UploadedFile => UploadedFile::fake()->image("p{$i}.jpg", 400, 300),
        range(1, $count),
    );
}

it('hands every numeric env value to the reader raw (strict config)', function (): void {
    foreach (array_keys(REVIEWS_VALUE_ENV) as $name) {
        Env::getRepository()->set($name, 'five');
    }

    $config = require __DIR__.'/../../../config/reviews.php';

    foreach (REVIEWS_VALUE_ENV as $path) {
        expect(data_get($config, $path))->toBe('five');
    }
});

it('ships real integer defaults when the env is unset (strict config)', function (): void {
    $config = require __DIR__.'/../../../config/reviews.php';

    expect($config['min_rating'])->toBe(1)
        ->and($config['max_rating'])->toBe(5)
        ->and($config['photos']['max'])->toBe(5)
        ->and($config['photos']['max_file_size'])->toBe(5 * 1024 * 1024)
        ->and($config['photos']['visibility'])->toBe('public');
});

it('refuses a junk or out-of-range rating scale (strict config)', function (string $key, mixed $value, string $message): void {
    config()->set($key, $value);

    expect(fn () => (new ValidatesRating)->execute(3))
        ->toThrow(InvalidConfigurationException::class, $message);
})->with([
    'min junk' => ['reviews.min_rating', 'five', 'Configuration value [reviews.min_rating] must be an integer, [five] given.'],
    'max junk' => ['reviews.max_rating', '5.5', 'Configuration value [reviews.max_rating] must be an integer, [5.5] given.'],
    'min negative' => ['reviews.min_rating', -1, 'Configuration value [reviews.min_rating] must be between 0 and 255, [-1] given.'],
    'max beyond the column' => ['reviews.max_rating', 300, 'Configuration value [reviews.max_rating] must be between 1 and 255, [300] given.'],
    'max below min' => ['reviews.max_rating', 0, 'Configuration value [reviews.max_rating] must be between 1 and 255, [0] given.'],
]);

it('reads a canonical integer string rating scale (strict config)', function (): void {
    config()->set('reviews.min_rating', '0');
    config()->set('reviews.max_rating', ' 10 ');

    (new ValidatesRating)->execute(0);
    (new ValidatesRating)->execute(10);

    expect(fn () => (new ValidatesRating)->execute(11))->toThrow(InvalidRatingException::class);
});

it('uses the 1-5 default scale when the keys are absent (strict config)', function (): void {
    config()->set('reviews.min_rating', null);
    config()->set('reviews.max_rating', null);

    expect(fn () => (new ValidatesRating)->execute(0))->toThrow(InvalidRatingException::class)
        ->and(fn () => (new ValidatesRating)->execute(6))->toThrow(InvalidRatingException::class);
});

it('refuses a junk or negative photo limit instead of reading it as unlimited (strict config)', function (mixed $value, string $message): void {
    config()->set('reviews.photos.max', $value);

    expect(fn () => strictReviewWithPhotos(strictPhotos(1)))
        ->toThrow(InvalidConfigurationException::class, $message);
})->with([
    'junk' => ['five', 'Configuration value [reviews.photos.max] must be an integer, [five] given.'],
    'blank' => ['', "Configuration value [reviews.photos.max] must be an integer, [''] given."],
    'negative' => ['-1', 'Configuration value [reviews.photos.max] must be at least 0, [-1] given.'],
]);

it('caps photos at the default five when the limit is absent (strict config)', function (): void {
    config()->set('reviews.photos.max', null);

    expect(fn () => strictReviewWithPhotos(strictPhotos(6)))->toThrow('at most 5');
});

it('reads a canonical integer string photo limit (strict config)', function (): void {
    config()->set('reviews.photos.max', '2');

    expect(fn () => strictReviewWithPhotos(strictPhotos(3)))->toThrow('at most 2');
});

it('refuses a visibility typo instead of making photos public (strict config)', function (mixed $value): void {
    config()->set('reviews.photos.visibility', $value);

    expect(fn () => strictPhotoReview()->resolveMediaBucket('photos'))
        ->toThrow(InvalidConfigurationException::class, 'Configuration value [reviews.photos.visibility] must be one of [public, private]');
})->with([
    'typo' => ['privat'],
    'capitalised' => ['Private'],
    'blank' => [''],
    'boolean' => [true],
]);

it('keeps photos public when the visibility is absent (strict config)', function (): void {
    config()->set('reviews.photos.visibility', null);

    expect(strictPhotoReview()->resolveMediaBucket('photos')?->getVisibility())->toBe('public');
});

it('refuses a junk or negative max file size (strict config)', function (mixed $value, string $message): void {
    config()->set('reviews.photos.max_file_size', $value);

    expect(fn () => strictPhotoReview()->resolveMediaBucket('photos'))
        ->toThrow(InvalidConfigurationException::class, $message);
})->with([
    'junk' => ['5MB', 'Configuration value [reviews.photos.max_file_size] must be an integer, [5MB] given.'],
    'negative' => [-5, 'Configuration value [reviews.photos.max_file_size] must be at least 0, [-5] given.'],
]);

it('applies a max file size written as an env string (strict config)', function (): void {
    config()->set('reviews.photos.max_file_size', '1024');

    expect(strictPhotoReview()->resolveMediaBucket('photos')?->getMaxFileSize())->toBe(1024);
});

it('applies the default max file size when it is absent (strict config)', function (): void {
    config()->set('reviews.photos.max_file_size', null);

    expect(strictPhotoReview()->resolveMediaBucket('photos')?->getMaxFileSize())->toBe(5 * 1024 * 1024);
});

it('refuses a blank or non-string photo storage setting (strict config)', function (string $key, mixed $value): void {
    config()->set('reviews.photos.visibility', 'private');
    config()->set($key, $value);

    expect(fn () => strictPhotoReview()->resolveMediaBucket('photos'))
        ->toThrow(InvalidConfigurationException::class, "Configuration value [{$key}] must be a non-empty string");
})->with([
    'bucket blank' => ['reviews.photos.bucket', ''],
    'bucket array' => ['reviews.photos.bucket', ['photos']],
    'disk blank' => ['reviews.photos.disk', ' '],
    'disk int' => ['reviews.photos.disk', 3],
    'private disk blank' => ['reviews.photos.private_disk', ''],
    'private disk bool' => ['reviews.photos.private_disk', false],
]);

it('uses the packaged bucket and private disk when they are absent (strict config)', function (): void {
    config()->set('reviews.photos.visibility', 'private');
    config()->set('reviews.photos.bucket', null);
    config()->set('reviews.photos.private_disk', null);

    $review = strictPhotoReview();

    expect($review->photosBucket())->toBe('photos')
        ->and($review->resolveMediaBucket('photos')?->getDisk())->toBe('local');
});

it('refuses a junk accepted mime type list (strict config)', function (mixed $value): void {
    config()->set('reviews.photos.accepted_mime_types', $value);

    expect(fn () => strictPhotoReview()->resolveMediaBucket('photos'))
        ->toThrow(InvalidConfigurationException::class, 'reviews.photos.accepted_mime_types');
})->with([
    'a string' => ['image/jpeg'],
    'a blank entry' => [['image/jpeg', '']],
    'a non-string entry' => [['image/jpeg', 42]],
]);

it('refuses a junk responsive width ladder (strict config)', function (mixed $value): void {
    config()->set('reviews.photos.responsive_widths', $value);

    expect(fn () => strictPhotoReview()->resolveMediaBucket('photos'))
        ->toThrow(InvalidConfigurationException::class, 'reviews.photos.responsive_widths');
})->with([
    'a string' => ['320,640'],
    'a zero width' => [[320, 0]],
    'a junk width' => [[320, 'wide']],
]);

it('reads a responsive width written as an integer string (strict config)', function (): void {
    config()->set('reviews.photos.responsive_widths', ['480', 960]);

    expect(strictPhotoReview()->resolveMediaBucket('photos')?->getResponsiveWidths())->toBe([480, 960]);
});

it('refuses a junk banned word list (strict config)', function (mixed $value): void {
    config()->set('reviews.moderation.banned_words', $value);

    expect(fn () => new WordListModerator)
        ->toThrow(InvalidConfigurationException::class, 'reviews.moderation.banned_words');
})->with([
    'a string' => ['spam,scam'],
    'a non-string entry' => [['spam', 7]],
]);

it('refuses a junk signed-url lifetime for private photos (strict config)', function (mixed $value, string $message): void {
    config()->set('reviews.photos.visibility', 'private');
    $review = Reviews::for(Entity::create())->by(Entity::create())->rating(5)->content('great')
        ->withPhoto(UploadedFile::fake()->image('a.jpg', 400, 300))->create();

    config()->set('media.temporary_url_default_lifetime', $value);

    expect(fn () => $review->firstPhotoUrl())->toThrow(InvalidConfigurationException::class, $message);
})->with([
    'junk' => ['five', 'Configuration value [media.temporary_url_default_lifetime] must be an integer, [five] given.'],
    'zero' => [0, 'Configuration value [media.temporary_url_default_lifetime] must be at least 1, [0] given.'],
]);

it('flags a broken setting in about instead of rendering a fallback (strict config)', function (): void {
    config()->set('reviews.photos.visibility', 'privat');
    config()->set('reviews.min_rating', 'one');
    config()->set('reviews.photos.max', 'lots');

    Artisan::call('about', ['--only' => 'reviews']);
    $output = Artisan::output();

    expect($output)->toMatch('/Photo visibility\W+INVALID/')
        ->and($output)->toMatch('/Rating scale\W+INVALID/')
        ->and($output)->toMatch('/Photo limits\W+INVALID/')
        ->and($output)->not->toMatch('/Photo visibility\W+public/');
});

it('flags a default status typo in about (strict config)', function (): void {
    config()->set('reviews.default_status', 'pendng');

    Artisan::call('about', ['--only' => 'reviews']);

    expect(Artisan::output())->toMatch('/Default status\W+INVALID/');
});
