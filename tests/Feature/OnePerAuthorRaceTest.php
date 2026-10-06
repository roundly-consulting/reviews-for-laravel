<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Reviews\Exceptions\InvalidReviewException;
use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Tests\Entity;
use RoundlyConsulting\Testing\Database\DriverMatrix;

/*
 * `one_per_author` was check-then-insert: the duplicate lookup ran outside the create
 * transaction, so two concurrent submissions by one author (a double-click, a retry) could both
 * find nothing and both insert. With the rule on, the create now takes the author's row lock and
 * re-checks for a duplicate inside the same transaction as the insert, so one author's
 * submissions serialize: the second one's lookup sees the first one's committed review.
 */

/**
 * Every statement the closure runs, lower-cased and unquoted, with the transaction level it ran at.
 *
 * @return list<array{sql: string, level: int}>
 */
function statementsDuring(callable $callback): array
{
    $log = [];

    DB::listen(function (QueryExecuted $query) use (&$log): void {
        // Quote-agnostic, so the same assertions hold on the sqlite, pgsql and mysql legs.
        $sql = str_replace(['"', '`'], '', strtolower($query->sql));
        $log[] = ['sql' => $sql, 'level' => $query->connection->transactionLevel()];
    });

    $callback();

    return $log;
}

/**
 * @param  list<array{sql: string, level: int}>  $log
 */
function firstStatement(array $log, callable $match): int
{
    foreach ($log as $index => $entry) {
        if ($match($entry['sql'])) {
            return $index;
        }
    }

    return -1;
}

it('locks the author, then re-checks and inserts inside one transaction', function (): void {
    config()->set('reviews.one_per_author', true);
    $author = Entity::create();
    $product = Entity::create();

    $log = statementsDuring(fn () => Reviews::for($product)->by($author)->content('Mine')->create());

    $lock = firstStatement($log, fn (string $sql): bool => str_starts_with($sql, 'select') && str_contains($sql, ' entities '));
    // The re-check is a locking `select id` since the 2026-10-06 chat review (C-3); its lock clause
    // is pinned in Regression/StaleSnapshotReadsTest.
    $recheck = firstStatement($log, fn (string $sql): bool => str_starts_with($sql, 'select id from reviews '));
    $insert = firstStatement($log, fn (string $sql): bool => str_starts_with($sql, 'insert into reviews '));

    expect($lock)->toBeGreaterThanOrEqual(0)
        ->and($recheck)->toBeGreaterThan($lock)
        ->and($insert)->toBeGreaterThan($recheck);

    foreach ([$lock, $recheck, $insert] as $index) {
        expect($log[$index]['level'])->toBeGreaterThan(0);
    }
});

it('takes no lock when one_per_author is off', function (): void {
    $log = statementsDuring(fn () => Reviews::for(Entity::create())->by(Entity::create())->content('Any')->create());

    expect(firstStatement($log, fn (string $sql): bool => str_starts_with($sql, 'select') && str_contains($sql, ' entities ')))->toBe(-1);
});

it('refuses the duplicate under the lock, rolling the second create back', function (): void {
    config()->set('reviews.one_per_author', true);
    $author = Entity::create();
    $product = Entity::create();

    Reviews::for($product)->by($author)->content('First')->create();

    expect(fn () => Reviews::for($product)->by($author)->content('Second')->create())
        ->toThrow(InvalidReviewException::class);

    expect(Review::query()->count())->toBe(1);
});

it('locks an author that lives on another connection in a transaction of its own', function (): void {
    config()->set('reviews.one_per_author', true);
    config()->set('database.connections.authors', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    Schema::connection('authors')->create('entities', function (Blueprint $table): void {
        $table->id();
    });

    $author = (new Entity)->setConnection('authors');
    $author->save();

    $levels = [];
    DB::connection('authors')->listen(function (QueryExecuted $query) use (&$levels): void {
        // listen() hooks the shared dispatcher, so keep to this connection's statements.
        if ($query->connectionName === 'authors' && str_contains(strtolower($query->sql), 'entities')) {
            $levels[] = $query->connection->transactionLevel();
        }
    });

    $review = Reviews::for(Entity::create())->by($author)->content('Cross-connection')->create();

    expect($review->exists)->toBeTrue()
        ->and($levels)->not->toBeEmpty()
        ->and(min($levels))->toBeGreaterThan(0);

    DB::purge('authors');
});

/*
 * The real-engine proof: while another session holds the author's row lock (a submission in
 * flight), a create waits on that lock — here it gives up after the lock timeout — before it
 * looks for a duplicate, and nothing is written.
 */
it('waits for the author row lock before looking for a duplicate', function (): void {
    config()->set('reviews.one_per_author', true);
    $author = Entity::create();
    $product = Entity::create();

    config()->set('database.connections.rival', config('database.connections.'.config('database.default')));
    $rival = DB::connection('rival');
    $rival->beginTransaction();
    $rival->table('entities')->where('id', $author->getKey())->lockForUpdate()->first();

    DriverMatrix::driver() === 'pgsql'
        ? DB::statement("set lock_timeout = '300ms'")
        : DB::statement('set session innodb_lock_wait_timeout = 1');

    try {
        Reviews::for($product)->by($author)->content('Racing')->create();
        $this->fail('The create did not wait for the author row lock.');
    } catch (QueryException $e) {
        expect(strtolower($e->getSql()))->toContain('entities')->toContain('for update');
    } finally {
        $rival->rollBack();
        DB::purge('rival');
    }

    expect(Review::query()->count())->toBe(0);
})->skip(fn (): bool => ! in_array(DriverMatrix::driver(), ['pgsql', 'mysql'], true), 'row locks need a real engine');
