<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Tests;

use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\MediaLibrary\MediaLibraryServiceProvider;
use RoundlyConsulting\Reviews\ReviewsServiceProvider;

/**
 * A host that swaps `reviews.model` does it in `config/reviews.php` — so the swap is in
 * place BEFORE the package's migrations run, not after (as an in-test `config()->set()`
 * swap is). That ordering is the whole point of this base case: `0002_create_review_votes`
 * resolves its foreign-key parent from the seam at migrate time, so a body-time swap
 * cannot see a bug in it.
 *
 * `defineEnvironment()` is deliberately NOT overridden. PackageTestCase does its whole job
 * there — `DriverMatrix::configure()` + `configBeforeBoot()` + the model swaps — so an
 * override without `parent::` decapitates the base case silently: no error, no red,
 * DriverMatrix simply never configured and the pgsql leg quietly running sqlite. This class
 * previously extended Orchestra directly and hand-wrote its own environment, which is how
 * it came to be the only suite in the package with `foreign_key_constraints` set at all.
 */
abstract class SwappedModelTestCase extends TestCase
{
    /**
     * Media-library is registered even though `reviews.photos.enabled` is off below: its table is
     * part of the documented install, and force-deleting a review purges its photos whatever the
     * switch says (chat review C-11), so a force-delete here queries `media` like a host's does.
     *
     * @return list<class-string<ServiceProvider>>
     */
    protected function packageProviders(): array
    {
        return [
            MediaLibraryServiceProvider::class,
            ReviewsServiceProvider::class,
        ];
    }

    /**
     * The host's own table exists before the package's migrations run — the ordering a real
     * install has, and the only ordering under which `0002_create_review_votes_table` can
     * resolve its parent through the seam.
     *
     * It was a `TenantReview::createTable()` call in a hand-rolled
     * `defineDatabaseMigrations()`; as a fixture migration it now runs through the migrator
     * and is dropped and rebuilt by the base case's reset like every other table.
     *
     * @return list<class-string<ServiceProvider>|string>
     */
    protected function migrationSources(): array
    {
        return [
            __DIR__.'/database/host-migrations',
            MediaLibraryServiceProvider::class,
            ReviewsServiceProvider::class,
            __DIR__.'/database/migrations',
        ];
    }

    /**
     * Note `array_merge(parent::configBeforeBoot(), …)`: dropping it would silently discard
     * the base case's media wiring — the same decapitation an un-parented
     * `defineEnvironment()` causes one level up.
     *
     * @return array<string, mixed>
     */
    protected function configBeforeBoot(): array
    {
        return array_merge(parent::configBeforeBoot(), [
            'reviews.model' => TenantReview::class,
            'reviews.vote_model' => TenantVote::class,
            'reviews.photos.enabled' => false,
        ]);
    }
}
