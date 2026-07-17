<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use RoundlyConsulting\Reviews\ReviewsServiceProvider;

/**
 * A host that swaps `reviews.model` does it in `config/reviews.php` — so the swap is in place
 * BEFORE the package's migrations run, not after (as the in-test `config()->set()` swaps do).
 *
 * Foreign keys are enforced here, as they are on every engine a host actually deploys on.
 */
abstract class SwappedModelTestCase extends Orchestra
{
    use RefreshDatabase;

    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return [ReviewsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            // Laravel's SQLite connector leaves PRAGMA foreign_keys OFF unless this is set, which is
            // why the rest of the suite never noticed a foreign key pointing at the wrong table.
            'foreign_key_constraints' => true,
        ]);

        $app['config']->set('reviews.model', TenantReview::class);
        $app['config']->set('reviews.photos.enabled', false);
    }

    protected function defineDatabaseMigrations(): void
    {
        // The host's own table exists before the package's migrations run.
        TenantReview::createTable();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Schema::create('entities', function (Blueprint $table): void {
            $table->id();
        });
    }
}
