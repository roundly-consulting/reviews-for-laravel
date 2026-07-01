<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use ReflectionClass;
use RoundlyConsulting\MediaLibrary\MediaLibraryServiceProvider;
use RoundlyConsulting\Reviews\ReviewsServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return [
            MediaLibraryServiceProvider::class,
            ReviewsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Media-library: fakeable public disk, GD driver, and a small responsive ladder so
        // variant generation stays fast under test. Review photos default to public.
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('media.disk', 'public');
        $app['config']->set('media.image_driver', 'gd');
        $app['config']->set('media.responsive.widths', [320, 640]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Media-library ships the `media` table review photos persist into.
        $mediaPackage = dirname((string) (new ReflectionClass(MediaLibraryServiceProvider::class))->getFileName(), 2);
        $this->loadMigrationsFrom($mediaPackage.'/database/migrations');

        Schema::create('entities', function (Blueprint $table): void {
            $table->id();
        });

        Schema::create('catalogs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('reviews_count')->default(0);
            $table->decimal('reviews_avg', 8, 4)->nullable();
        });
    }
}
