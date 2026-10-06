<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Observers\ReviewAggregateObserver;
use RoundlyConsulting\Reviews\Tests\Catalog;
use RoundlyConsulting\Reviews\Tests\Entity;
use RoundlyConsulting\Testing\Database\DriverMatrix;
use RoundlyConsulting\Testing\Fixtures\LockRecordingGrammar;

/*
 * The cached `reviews_count` / `reviews_avg` were recounted read-then-write with no lock: an
 * approved review committed between one recount's count and its write was lost, and the pair
 * could disagree (the average read after the other commit, the count before). The recount now
 * runs in a transaction holding the subject's row lock and counts with locking reads; inside a
 * transaction the subject is locked before the review row is even written, so concurrent
 * writers of one subject always take their locks in the same order.
 */

beforeEach(function (): void {
    config()->set('reviews.cache_aggregates', true);

    // The provider booted with caching off; hang the observer as a host with it on would have it.
    Review::observe(ReviewAggregateObserver::class);
});

/**
 * Every statement the closure runs on the default connection — lower-cased, unquoted, with the
 * lock clause in one spelling whatever the engine — and the transaction level it ran at.
 *
 * @return list<array{sql: string, level: int}>
 */
function aggregateStatementsDuring(callable $callback): array
{
    $connection = DB::connection();

    if ($connection->getDriverName() === 'sqlite') {
        $connection->setQueryGrammar(new LockRecordingGrammar($connection));
    }

    $log = [];

    DB::listen(function (QueryExecuted $query) use (&$log): void {
        $log[] = [
            'sql' => str_replace(
                ['"', '`', '/* lock-for-update */', '/* lock-shared */', 'lock in share mode'],
                ['', '', 'for update', 'for share', 'for share'],
                strtolower($query->sql),
            ),
            'level' => $query->connection->transactionLevel(),
        ];
    });

    $callback();

    return $log;
}

/**
 * @param  list<array{sql: string, level: int}>  $log
 */
function firstAggregateStatement(array $log, callable $match): int
{
    foreach ($log as $index => $entry) {
        if ($match($entry['sql'])) {
            return $index;
        }
    }

    return -1;
}

/**
 * @param  list<array{sql: string, level: int}>  $log
 * @return array{lock: int, count: int, write: int}
 */
function recountStatements(array $log): array
{
    return [
        'lock' => firstAggregateStatement($log, fn (string $sql): bool => str_contains($sql, 'from catalogs') && str_contains($sql, 'for update')),
        'count' => firstAggregateStatement($log, fn (string $sql): bool => str_contains($sql, 'count(*)') && str_contains($sql, 'from reviews')),
        'write' => firstAggregateStatement($log, fn (string $sql): bool => str_starts_with($sql, 'update catalogs ')),
    ];
}

it('locks the subject before writing a review, then recounts under that lock with locking reads', function (): void {
    $catalog = Catalog::query()->create();

    $log = aggregateStatementsDuring(fn () => Reviews::for($catalog)->by(Entity::create())->rating(4)->approved()->create());

    ['lock' => $lock, 'count' => $count, 'write' => $write] = recountStatements($log);
    $insert = firstAggregateStatement($log, fn (string $sql): bool => str_starts_with($sql, 'insert into reviews '));

    expect($lock)->toBeGreaterThanOrEqual(0)
        ->and($insert)->toBeGreaterThan($lock)
        ->and($count)->toBeGreaterThan($insert)
        ->and($write)->toBeGreaterThan($count)
        ->and($log[$count]['sql'])->toContain('for share');

    foreach ([$lock, $insert, $count, $write] as $index) {
        expect($log[$index]['level'])->toBeGreaterThan(0);
    }

    expect($catalog->fresh()?->reviews_count)->toBe(1);
});

it('recounts an approval in a transaction of its own, under the subject lock', function (): void {
    $catalog = Catalog::query()->create();
    $review = Reviews::for($catalog)->by(Entity::create())->rating(2)->create();

    $log = aggregateStatementsDuring(fn () => Reviews::approve($review));

    ['lock' => $lock, 'count' => $count, 'write' => $write] = recountStatements($log);

    expect($lock)->toBeGreaterThanOrEqual(0)
        ->and($count)->toBeGreaterThan($lock)
        ->and($write)->toBeGreaterThan($count)
        ->and($log[$count]['sql'])->toContain('for share');

    foreach ([$lock, $count, $write] as $index) {
        expect($log[$index]['level'])->toBeGreaterThan(0);
    }

    expect($catalog->fresh()?->reviews_count)->toBe(1)
        ->and((float) $catalog->fresh()?->reviews_avg)->toBe(2.0);
});

/*
 * The real-engine proof: a second request's approved review of the same subject arrives while the
 * first request is between its count and its write. Before the fix it committed right there and
 * the first request then wrote a count that missed it. Now it has to wait for the subject lock
 * (here it gives up after the lock timeout and is retried once the first request is done), and
 * the cache ends equal to the live aggregates.
 */
it('keeps the cached aggregates whole when a rival approved review races the recount', function (): void {
    $catalog = Catalog::query()->create();
    $main = (string) config('database.default');

    config()->set('database.connections.rival', config("database.connections.{$main}"));
    $rival = DB::connection('rival');

    DriverMatrix::driver() === 'pgsql'
        ? $rival->statement("set lock_timeout = '300ms'")
        : $rival->statement('set session innodb_lock_wait_timeout = 1');

    $rivalCreate = function () use ($catalog): void {
        DB::setDefaultConnection('rival');

        try {
            Reviews::for(Catalog::on('rival')->findOrFail($catalog->getKey()))
                ->by(Entity::on('rival')->create())
                ->rating(2)
                ->approved()
                ->create();
        } finally {
            DB::setDefaultConnection('testing');
        }
    };

    $raced = false;
    $blocked = false;

    DB::listen(function (QueryExecuted $query) use (&$raced, &$blocked, $main, $rivalCreate): void {
        $sql = strtolower($query->sql);

        if ($raced || $query->connectionName !== $main || ! str_contains($sql, 'count(*)') || ! str_contains($sql, 'reviews')) {
            return;
        }

        $raced = true;

        try {
            $rivalCreate();
        } catch (QueryException) {
            $blocked = true;
        }
    });

    try {
        Reviews::for($catalog)->by(Entity::create())->rating(4)->approved()->create();

        if ($blocked) {
            $rivalCreate();
        }
    } finally {
        DB::purge('rival');
    }

    $fresh = $catalog->fresh();

    expect($raced)->toBeTrue()
        ->and($blocked)->toBeTrue()
        ->and(Reviews::for($catalog)->count())->toBe(2)
        ->and($fresh?->reviews_count)->toBe(2)
        ->and((float) $fresh?->reviews_avg)->toBe(3.0);
})->skip(fn (): bool => ! in_array(DriverMatrix::driver(), ['pgsql', 'mysql'], true), 'row locks need a real engine');
