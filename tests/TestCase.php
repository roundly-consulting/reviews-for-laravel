<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Tests;

use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\MediaLibrary\MediaLibraryServiceProvider;
use RoundlyConsulting\Reviews\ReviewsServiceProvider;
use RoundlyConsulting\Testing\PackageTestCase;

abstract class TestCase extends PackageTestCase
{
    /**
     * Every provider the suite really needs, in registration order. Media-library is a
     * hard `require` a host would auto-discover, and review photos genuinely run on it.
     *
     * `enums-for-laravel` and `package-toolkit-for-laravel` are hard `require`s too, but
     * the first ships no provider and the second is a base class rather than a registered
     * package — so the list is genuinely two entries.
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
     * The migrations, named by **provider class** — never by filename.
     *
     * This replaces a hand-rolled `defineDatabaseMigrations()` that reflected on
     * MediaLibraryServiceProvider to find its package root and then guessed
     * `/database/migrations` beneath it: exactly what the base case's
     * `LoadsProviderMigrations` concern does once, correctly, for the whole fleet.
     *
     * The `entities` / `catalogs` fixture tables were built by bare `Schema::create()`
     * calls here, i.e. outside the migrator and outside every reset. They are fixture
     * migrations now, so the base case's drop-and-remigrate reset owns them like any
     * other table.
     *
     * @return list<class-string<ServiceProvider>|string>
     */
    protected function migrationSources(): array
    {
        return [
            MediaLibraryServiceProvider::class,
            ReviewsServiceProvider::class,
            __DIR__.'/database/migrations',
        ];
    }

    /**
     * Media-library: a fakeable public disk, the GD driver, and a small responsive ladder
     * so variant generation stays fast. Set here rather than in `defineEnvironment()`
     * because the base case does its whole job there (DriverMatrix::configure + these keys
     * + the model swaps) — an override without `parent::` decapitates it silently: no
     * error, no red, DriverMatrix simply never configured, and the pgsql leg quietly runs
     * sqlite.
     *
     * `app.key` is gone: `RefreshDatabase` is gone with it (see below) and Testbench
     * already seeds a key. It was only ever here because this file hand-rolled the whole
     * environment.
     *
     * @return array<string, mixed>
     */
    protected function configBeforeBoot(): array
    {
        return [
            'media.disk' => 'public',
            'media.image_driver' => 'gd',
            'media.responsive.widths' => [320, 640],
        ];
    }

    /*
     * `RefreshDatabase` is deliberately NOT used, and dropping it is not a detail.
     *
     * It migrates once and wraps every test in a transaction, which adds a transaction
     * level — and the fleet's LockRecorder asserts on `transactionDepth`, the exact datum
     * that condemned the deleted LockedUpdate helper. The base case resets by dropping
     * every table and re-migrating instead: pure DDL, no transaction, and no `down()`
     * (roundly packages migrate forward only).
     *
     * The connection block this file used to hand-write is gone with it. It hard-coded
     * sqlite `:memory:` and — the reason it matters here — omitted
     * `foreign_key_constraints`, so Laravel's SQLite connector left `PRAGMA foreign_keys`
     * OFF and this suite could not see a foreign-key violation at all. That is not a
     * hypothetical: reviews' own SwappedModelTestCase carries a comment saying exactly
     * that, and it is how the votes foreign key came to point at the wrong table. The base
     * case sets the pragma `true` for every suite, so both of this package's real FK edges
     * are now enforced on the ordinary sqlite leg.
     */
}
