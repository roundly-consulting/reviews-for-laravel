<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RoundlyConsulting\PackageToolkit\Support\MigrationPublisher;

/**
 * The package's migrations are publish-only: the host publishes them and runs `php artisan
 * migrate`, so the DIRECTORY SORT ORDER of `database/migrations` *is* the order they run in.
 *
 * `review_votes.review_id` carries a real foreign key onto `reviews`, so votes must be created
 * after reviews. It was not: the directory sorted `create_review_votes_table.php` first, and a
 * fresh install died on PostgreSQL/MySQL with `relation "reviews" does not exist`. SQLite silently
 * accepts a CREATE TABLE that references a missing parent, which is why the whole suite stayed
 * green over an install nobody could perform.
 */
const MIGRATIONS_DIR = __DIR__.'/../../database/migrations';

/** @return list<string> The package's `.php` migration sources, in publish (= run) order. */
function migrationSources(): array
{
    $files = glob(MIGRATIONS_DIR.'/*.php') ?: [];

    sort($files);

    return array_values($files);
}

/** The table a migration source creates, or null when it only alters. */
function createdTable(string $source): ?string
{
    preg_match("/Schema::create\(\s*'([a-z_]+)'/", (string) file_get_contents($source), $m);

    return $m[1] ?? null;
}

/** The table a migration source alters, or null when it creates. */
function alteredTable(string $source): ?string
{
    preg_match("/Schema::table\(\s*'([a-z_]+)'/", (string) file_get_contents($source), $m);

    return $m[1] ?? null;
}

/**
 * Every foreign-key parent a migration source references, in all three of the forms Laravel
 * accepts: `->constrained('parent')`, a bare `->constrained()` (parent derived from the column
 * name), and the long-hand `->references('id')->on('parent')`.
 *
 * @return list<string>
 */
function foreignKeyParents(string $source): array
{
    $body = (string) file_get_contents($source);
    $parents = [];

    preg_match_all("/->constrained\(\s*'([a-z_]+)'/", $body, $explicit);
    $parents = [...$parents, ...$explicit[1]];

    preg_match_all("/->on\(\s*'([a-z_]+)'\s*\)/", $body, $longhand);
    $parents = [...$parents, ...$longhand[1]];

    // A bare `->constrained()` derives the parent table from the column name: `review_id` → `reviews`.
    preg_match_all("/->foreign(?:Id|Uuid|Ulid)\(\s*'([a-z_]+)'\s*\)((?:(?!;).)*)/s", $body, $columns, PREG_SET_ORDER);

    foreach ($columns as [, $column, $chain]) {
        if (preg_match('/->constrained\(\s*\)/', $chain) === 1) {
            $parents[] = Str::plural(Str::beforeLast($column, '_id'));
        }
    }

    return array_values(array_unique($parents));
}

it('sorts every foreign key target before the migration that references it', function (): void {
    $sources = migrationSources();
    $creates = [];

    foreach ($sources as $index => $source) {
        $table = createdTable($source);

        if ($table !== null) {
            $creates[$table] = $index;
        }
    }

    $edges = 0;

    foreach ($sources as $index => $source) {
        foreach (foreignKeyParents($source) as $parent) {
            $edges++;

            expect(array_key_exists($parent, $creates))->toBeTrue(sprintf(
                '%s references "%s", which no migration creates.',
                basename($source),
                $parent,
            ));

            // A self-referencing key sorts with its own file; every other parent must sort strictly first.
            $limit = createdTable($source) === $parent ? $index : $index - 1;

            expect($creates[$parent])->toBeLessThanOrEqual($limit, sprintf(
                '%s (position %d) references "%s", created at position %d.',
                basename($source),
                $index,
                $parent,
                $creates[$parent],
            ));
        }
    }

    // The engine-independent pin above is the ONLY check that catches a bad order on SQLite, so it
    // must never pass because it found no edges to check.
    expect($edges)->toBe(2);
});

it('sorts every alter after the migration that creates its table', function (): void {
    $sources = migrationSources();
    $creates = [];

    foreach ($sources as $index => $source) {
        $table = createdTable($source);

        if ($table !== null) {
            $creates[$table] = $index;
        }
    }

    foreach ($sources as $index => $source) {
        $table = alteredTable($source);

        if ($table === null || ! isset($creates[$table])) {
            continue;
        }

        expect($creates[$table])->toBeLessThan($index);
    }
})->throwsNoExceptions();

it('migrates the published files into a fresh empty database', function (): void {
    $directory = sys_get_temp_dir().'/reviews-publish-'.Str::random(8);
    mkdir($directory);

    // Copy each source to the filename the toolkit publishes it under: one timestamp base,
    // +1s per file, in directory order.
    $timestamp = Carbon::now();
    $offset = 0;

    foreach (migrationSources() as $source) {
        copy($source, MigrationPublisher::destination(
            MigrationPublisher::nameFor($source),
            $directory,
            $timestamp->copy()->addSeconds($offset++),
        ));
    }

    $database = $directory.'/host.sqlite';
    touch($database);

    config()->set('database.connections.host', [
        'driver' => 'sqlite',
        'database' => $database,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);

    Artisan::call('migrate', [
        '--database' => 'host',
        '--path' => $directory,
        '--realpath' => true,
    ]);

    $connection = DB::connection('host');

    expect($connection->getSchemaBuilder()->hasTable('reviews'))->toBeTrue()
        ->and($connection->getSchemaBuilder()->hasTable('review_votes'))->toBeTrue();

    // The foreign keys really are emitted, so the CREATE order above is load-bearing.
    $votesKeys = $connection->select('pragma foreign_key_list(review_votes)');
    $reviewKeys = $connection->select('pragma foreign_key_list(reviews)');

    expect(array_map(static fn (object $key): string => (string) $key->table, $votesKeys))->toBe(['reviews'])
        ->and(array_map(static fn (object $key): string => (string) $key->table, $reviewKeys))->toBe(['reviews']);

    // And the published timestamps preserve the dependency order.
    $published = glob($directory.'/*.php') ?: [];
    sort($published);

    expect(array_map(
        static fn (string $file): string => MigrationPublisher::nameFor($file),
        $published,
    ))->toBe(['0001_create_reviews_table', '0002_create_review_votes_table']);

    $connection->disconnect();
    array_map(unlink(...), glob($directory.'/*') ?: []);
    rmdir($directory);
});
