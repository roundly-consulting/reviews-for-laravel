<?php

declare(strict_types=1);

use Illuminate\Support\Env;
use Illuminate\Support\Facades\Artisan;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Reviews\DataTransferObjects\UpdateReviewData;
use RoundlyConsulting\Reviews\Events\ReviewApproved;
use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\Listeners\WarmReviewPhotoVariants;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\ReviewsServiceProvider;
use RoundlyConsulting\Reviews\Tests\Entity;

/**
 * `(bool) env('X')` read every non-empty string but "0" as true, so `REVIEWS_PHOTOS_ENABLED=off`
 * kept photos on and a typo silently picked a side. The config file now hands the raw env value
 * on, and every read goes through the toolkit's strict reader: a typo fails loudly.
 */
const REVIEWS_SWITCH_ENV = [
    'REVIEWS_AUTO_APPROVE' => 'auto_approve',
    'REVIEWS_RESET_STATUS_ON_EDIT' => 'reset_status_on_edit',
    'REVIEWS_ONE_PER_AUTHOR' => 'one_per_author',
    'REVIEWS_REGISTER_FACADE_ALIAS' => 'register_facade_alias',
    'REVIEWS_CACHE_AGGREGATES' => 'cache_aggregates',
    'REVIEWS_PHOTOS_ENABLED' => 'photos.enabled',
    'REVIEWS_PHOTOS_WARM_ON_APPROVAL' => 'photos.warm_on_approval',
];

afterEach(function (): void {
    foreach (array_keys(REVIEWS_SWITCH_ENV) as $name) {
        Env::getRepository()->clear($name);
    }
});

/**
 * @return array<string, mixed>
 */
function shippedReviewsConfig(): array
{
    return require __DIR__.'/../../../config/reviews.php';
}

it('hands every switch to the reader raw (strict config)', function (): void {
    foreach (array_keys(REVIEWS_SWITCH_ENV) as $name) {
        Env::getRepository()->set($name, 'disabled');
    }

    $config = shippedReviewsConfig();

    foreach (REVIEWS_SWITCH_ENV as $path) {
        expect(data_get($config, $path))->toBe('disabled');
    }
});

it('ships real boolean defaults when the env is unset', function (): void {
    $config = shippedReviewsConfig();

    expect($config['auto_approve'])->toBeFalse()
        ->and($config['reset_status_on_edit'])->toBeTrue()
        ->and($config['one_per_author'])->toBeFalse()
        ->and($config['register_facade_alias'])->toBeTrue()
        ->and($config['cache_aggregates'])->toBeFalse()
        ->and($config['photos']['enabled'])->toBeTrue()
        ->and($config['photos']['warm_on_approval'])->toBeTrue();
});

it('reads an env "off" as off', function (): void {
    Env::getRepository()->set('REVIEWS_PHOTOS_ENABLED', 'off');
    config()->set('reviews', shippedReviewsConfig());

    expect(Review::reviewPhotosEnabled())->toBeFalse();
});

it('throws on a switch typo instead of reading it as the default (strict config)', function (string $key, Closure $read): void {
    config()->set($key, 'disabled');

    expect($read)->toThrow(
        InvalidConfigurationException::class,
        "Configuration value [{$key}] must be a boolean (true/false, 1/0, on/off or yes/no), [disabled] given.",
    );
})->with([
    'auto_approve' => ['reviews.auto_approve', fn () => Reviews::for(Entity::create())->by(Entity::create())->content('x')->create()],
    'one_per_author' => ['reviews.one_per_author', fn () => Reviews::for(Entity::create())->by(Entity::create())->content('x')->create()],
    'reset_status_on_edit' => ['reviews.reset_status_on_edit', function (): void {
        $review = Reviews::for(Entity::create())->by(Entity::create())->content('x')->create();

        Reviews::update($review, new UpdateReviewData(content: 'changed'));
    }],
    'photos.enabled' => ['reviews.photos.enabled', fn (): bool => Review::reviewPhotosEnabled()],
    'photos.warm_on_approval' => ['reviews.photos.warm_on_approval', fn () => (new WarmReviewPhotoVariants)->handle(new ReviewApproved(new Review))],
    'cache_aggregates (boot)' => ['reviews.cache_aggregates', function (): void {
        $provider = new ReviewsServiceProvider(app());
        $provider->register();
        $provider->boot();
    }],
]);

it('labels the facade alias the way the toolkit registers it (strict config)', function (mixed $value, string $label): void {
    config()->set('reviews.register_facade_alias', $value);

    Artisan::call('about', ['--only' => 'reviews']);

    expect(Artisan::output())->toMatch('/Facade alias\W+'.$label.'\b/');
})->with([
    'off' => ['off', 'DISABLED'],
    'string zero' => ['0', 'DISABLED'],
    'on' => ['on', 'Reviews'],
    'renamed' => ['Ratings', 'Ratings'],
    'null' => [null, 'DISABLED'],
]);

it('refuses a junk facade alias switch (strict config)', function (): void {
    config()->set('reviews.register_facade_alias', 2);

    expect(fn () => (new ReviewsServiceProvider(app()))->register())
        ->toThrow(InvalidConfigurationException::class, 'reviews.register_facade_alias');
});
