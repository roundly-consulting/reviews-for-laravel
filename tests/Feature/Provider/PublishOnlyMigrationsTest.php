<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use Orchestra\Testbench\Foundation\Application;
use RoundlyConsulting\PackageToolkit\Support\MigrationPublisher;
use RoundlyConsulting\Reviews\ReviewsServiceProvider;

use function Orchestra\Testbench\package_path;

/**
 * Migrations are publish-only (fleet policy): the package declares them, the host publishes them
 * and runs `php artisan migrate`. Nothing is auto-loaded, and the publish tags a host already
 * scripted stay byte-identical.
 */
it('never auto-loads its migrations', function (): void {
    // A clean app with ONLY this package's provider — the suite's own TestCase explicitly loads the
    // migrations (it has to, now that nothing auto-discovers them), so it cannot answer this.
    $app = Application::create(
        basePath: package_path('vendor/orchestra/testbench-core/laravel'),
        options: [
            'enables_package_discoveries' => false,
            'extra' => ['providers' => [ReviewsServiceProvider::class]],
        ],
    );

    expect($app->make('migrator')->paths())->toBe([]);
});

it('publishes each migration under the reviews-migrations tag, timestamp-injected', function (): void {
    $published = ServiceProvider::pathsToPublish(ReviewsServiceProvider::class, 'reviews-migrations');

    $sources = array_keys($published);
    sort($sources);

    expect(array_map(basename(...), $sources))->toBe([
        '0001_create_reviews_table.php',
        '0002_create_review_votes_table.php',
    ]);

    foreach ($published as $source => $destination) {
        expect(dirname($destination))->toBe(database_path('migrations'))
            ->and(basename($destination))->toMatch(
                '/^\d{4}_\d{2}_\d{2}_\d{6}_'.preg_quote(MigrationPublisher::nameFor((string) $source), '/').'\.php$/'
            );
    }

    // The dependency order survives publishing: the reviews table stamps before the votes table.
    $destinations = array_values($published);
    sort($destinations);

    expect(array_map(
        static fn (string $file): string => MigrationPublisher::nameFor($file),
        $destinations,
    ))->toBe(['0001_create_reviews_table', '0002_create_review_votes_table']);
});

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
