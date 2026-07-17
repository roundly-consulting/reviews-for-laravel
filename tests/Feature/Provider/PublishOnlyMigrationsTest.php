<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Reviews\ReviewsServiceProvider;

/**
 * Migrations are publish-only (fleet policy): the package declares them, the host publishes them
 * and runs `php artisan migrate`. Nothing is auto-loaded, and the publish tags a host already
 * scripted stay byte-identical.
 */
it('keeps the aggregate stub on its own publish tag', function (): void {
    $published = ServiceProvider::pathsToPublish(ReviewsServiceProvider::class, 'reviews-aggregate-migrations');

    expect($published)->toHaveCount(1);

    $source = (string) array_key_first($published);

    expect(basename($source))->toBe('add_review_aggregates_to_reviewable_table.php.stub')
        ->and(basename((string) $published[$source]))->toMatch(
            '/^\d{4}_\d{2}_\d{2}_\d{6}_add_review_aggregates_to_reviewable_table\.php$/'
        );

    // It is an opt-in stub the host re-publishes once per reviewable table, so it must NOT be folded
    // into the reviews-migrations tag every host runs.
    $migrations = ServiceProvider::pathsToPublish(ReviewsServiceProvider::class, 'reviews-migrations');

    expect(array_keys($migrations))->not->toContain($source);
});

it('keeps every publish tag a host already scripted', function (): void {
    expect(ServiceProvider::publishableGroups())->toContain(
        'reviews-config',
        'reviews-migrations',
        'reviews-aggregate-migrations',
        'reviews-translations',
        'reviews-resources',
    );
});
