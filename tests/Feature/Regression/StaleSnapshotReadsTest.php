<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Reviews\Exceptions\InvalidReviewException;
use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Tests\Entity;
use RoundlyConsulting\Testing\Database\DriverMatrix;
use RoundlyConsulting\Testing\Fixtures\LockRecordingGrammar;

/*
 * The duplicate re-check and the vote tallies ran as plain reads after their row lock. Inside a
 * host's enclosing transaction under MySQL's default REPEATABLE READ, a plain read answers from
 * the snapshot the transaction's first read fixed — before the lock was granted — so it missed
 * the review (or vote) the lock had just waited for: a second review despite `one_per_author`,
 * a `helpful_count` short by the concurrent vote. Both are locking reads now, which always see
 * the latest committed rows.
 */

/**
 * Every statement the closure runs on the default connection — lower-cased, unquoted, with the
 * lock clause in one spelling whatever the engine (SQLite's dropped lock made visible as a
 * marker) — and the transaction level it ran at.
 *
 * @return list<array{sql: string, level: int}>
 */
function lockingStatementsDuring(callable $callback): array
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
 * @return list<int>
 */
function lockingStatementIndexes(array $log, callable $match): array
{
    return array_keys(array_filter($log, fn (array $entry): bool => $match($entry['sql'])));
}

it('re-checks for a duplicate with a locking read under the author lock', function (): void {
    config()->set('reviews.one_per_author', true);
    $author = Entity::create();
    $product = Entity::create();

    $log = lockingStatementsDuring(fn () => Reviews::for($product)->by($author)->content('Mine')->create());

    $lock = lockingStatementIndexes($log, fn (string $sql): bool => str_contains($sql, 'from entities') && str_contains($sql, 'for update'))[0] ?? -1;
    $recheck = lockingStatementIndexes($log, fn (string $sql): bool => str_starts_with($sql, 'select') && str_contains($sql, 'from reviews') && str_contains($sql, 'for update'))[0] ?? -1;
    $insert = lockingStatementIndexes($log, fn (string $sql): bool => str_starts_with($sql, 'insert into reviews'))[0] ?? -1;

    expect($lock)->toBeGreaterThanOrEqual(0)
        ->and($recheck)->toBeGreaterThan($lock)
        ->and($insert)->toBeGreaterThan($recheck);

    foreach ([$lock, $recheck, $insert] as $index) {
        expect($log[$index]['level'])->toBeGreaterThan(0);
    }
});

it('counts the votes with locking reads under the review lock', function (): void {
    $review = Review::factory()->create();

    $log = lockingStatementsDuring(fn () => Reviews::vote($review, Entity::create()));

    $lock = lockingStatementIndexes($log, fn (string $sql): bool => str_contains($sql, 'from reviews') && str_contains($sql, 'for update'))[0] ?? -1;
    $counts = lockingStatementIndexes($log, fn (string $sql): bool => str_contains($sql, 'count(') && str_contains($sql, 'review_votes'));

    expect($lock)->toBeGreaterThanOrEqual(0)
        ->and($counts)->toHaveCount(2);

    foreach ($counts as $index) {
        expect($index)->toBeGreaterThan($lock)
            ->and($log[$index]['sql'])->toContain('for share')
            ->and($log[$index]['level'])->toBeGreaterThan(0);
    }
});

/*
 * The real-engine proofs, on MySQL: the host's transaction reads first (fixing its REPEATABLE READ
 * snapshot), then a second session commits, then the package's write takes its lock. PostgreSQL's
 * default READ COMMITTED reads fresh per statement and never had the gap.
 */

/** A copy of the default connection: a second session on the same database. */
function rivalSession(): string
{
    config()->set('database.connections.rival', config('database.connections.'.config('database.default')));

    return 'rival';
}

/** Run package code on the rival session, as a second request would. */
function onRivalSession(Closure $callback): mixed
{
    $main = (string) config('database.default');
    DB::setDefaultConnection('rival');

    try {
        return $callback();
    } finally {
        DB::setDefaultConnection($main);
    }
}

it('refuses a duplicate committed after the host transaction fixed its snapshot', function (): void {
    config()->set('reviews.one_per_author', true);
    $author = Entity::create();
    $product = Entity::create();
    $rival = rivalSession();

    DB::beginTransaction();

    try {
        // The host's own first read in its transaction: the snapshot is fixed here.
        Review::query()->count();

        onRivalSession(fn () => Reviews::for(Entity::on($rival)->findOrFail($product->getKey()))
            ->by(Entity::on($rival)->findOrFail($author->getKey()))
            ->content('First')
            ->create());

        expect(fn () => Reviews::for($product)->by($author)->content('Second')->create())
            ->toThrow(InvalidReviewException::class, (string) trans('reviews::messages.review.duplicate'));
    } finally {
        DB::rollBack();
        DB::purge($rival);
    }

    expect(Review::query()->count())->toBe(1);
})->skip(fn (): bool => DriverMatrix::driver() !== 'mysql', 'a REPEATABLE READ snapshot taken before the lock is MySQL\'s default');

it('counts a vote committed after the host transaction fixed its snapshot', function (): void {
    $review = Review::factory()->create();
    $rival = rivalSession();

    DB::beginTransaction();

    try {
        Review::query()->count();

        onRivalSession(fn () => Reviews::vote(Review::on($rival)->findOrFail($review->getKey()), Entity::on($rival)->create()));

        Reviews::vote($review, Entity::create());

        DB::commit();
    } finally {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        DB::purge($rival);
    }

    expect($review->fresh()?->helpful_count)->toBe(2)
        ->and($review->helpful_count)->toBe(2);
})->skip(fn (): bool => DriverMatrix::driver() !== 'mysql', 'a REPEATABLE READ snapshot taken before the lock is MySQL\'s default');
