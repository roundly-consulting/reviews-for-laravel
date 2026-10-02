<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Models\ReviewVote;
use RoundlyConsulting\Reviews\Tests\Entity;
use RoundlyConsulting\Testing\Database\DriverMatrix;
use RoundlyConsulting\Testing\Fixtures\LockRecordingGrammar;

/*
 * The helpful tallies used to be recounted, then written back with `$review->save()`: a slower
 * recount could overwrite a faster one's newer count, the save persisted whatever else was dirty
 * on the model the caller handed in, and every vote bumped the review's `updated_at`. A vote
 * change and its recount are now one transaction holding the review's row lock, and the write
 * touches the two tally columns only.
 */

afterEach(fn () => CarbonImmutable::setTestNow());

/**
 * Every statement the closure runs — lower-cased, unquoted, with SQLite's dropped lock made
 * visible as a marker — and the transaction level it ran at.
 *
 * @return list<array{sql: string, level: int}>
 */
function voteStatements(callable $callback): array
{
    $connection = DB::connection();

    if ($connection->getDriverName() === 'sqlite') {
        // Stock SQLite compiles lockForUpdate() to nothing; this grammar leaves a comment instead.
        $connection->setQueryGrammar(new LockRecordingGrammar($connection));
    }

    $log = [];

    DB::listen(function (QueryExecuted $query) use (&$log): void {
        $log[] = [
            'sql' => str_replace(['"', '`', '/* lock-for-update */'], ['', '', 'for update'], strtolower($query->sql)),
            'level' => $query->connection->transactionLevel(),
        ];
    });

    $callback();

    return $log;
}

/**
 * @param  list<array{sql: string, level: int}>  $log
 */
function voteStatementIndex(array $log, callable $match): int
{
    foreach ($log as $index => $entry) {
        if ($match($entry['sql'])) {
            return $index;
        }
    }

    return -1;
}

it('locks the review before changing a vote, and recounts under that lock', function (string $verb): void {
    $review = Review::factory()->create();
    $voter = Entity::create();

    if ($verb === 'removeVote') {
        Reviews::vote($review, $voter);
    }

    $log = voteStatements(fn () => $verb === 'vote' ? Reviews::vote($review, $voter, false) : Reviews::removeVote($review, $voter));

    $lock = voteStatementIndex($log, fn (string $sql): bool => str_starts_with($sql, 'select') && str_contains($sql, 'from reviews ') && str_contains($sql, 'for update'));
    $change = voteStatementIndex($log, fn (string $sql): bool => preg_match('/^(insert into|update|delete from) review_votes /', $sql) === 1);
    $count = voteStatementIndex($log, fn (string $sql): bool => str_starts_with($sql, 'select count') && str_contains($sql, 'review_votes'));
    $write = voteStatementIndex($log, fn (string $sql): bool => str_starts_with($sql, 'update reviews '));

    expect($lock)->toBeGreaterThanOrEqual(0)
        ->and($change)->toBeGreaterThan($lock)
        ->and($count)->toBeGreaterThan($change)
        ->and($write)->toBeGreaterThan($count);

    foreach ([$lock, $change, $count, $write] as $index) {
        expect($log[$index]['level'])->toBeGreaterThan(0);
    }
})->with(['vote', 'removeVote']);

it('writes only the tallies, never other unsaved changes on the review', function (): void {
    $review = Review::factory()->create(['title' => 'Original']);
    $review->title = 'Unsaved draft';

    Reviews::vote($review, Entity::create());

    expect($review->fresh()?->title)->toBe('Original')
        ->and($review->fresh()?->helpful_count)->toBe(1)
        ->and($review->helpful_count)->toBe(1)
        ->and($review->isDirty('title'))->toBeTrue()
        ->and($review->isDirty(['helpful_count', 'unhelpful_count']))->toBeFalse();
});

it('does not touch the review updated_at for a vote', function (): void {
    CarbonImmutable::setTestNow('2026-01-01 10:00:00');
    $review = Review::factory()->create();
    $updatedAt = $review->fresh()?->updated_at;

    CarbonImmutable::setTestNow('2026-01-02 10:00:00');
    $voter = Entity::create();
    Reviews::vote($review, $voter);
    Reviews::vote($review, $voter, false);
    Reviews::removeVote($review, $voter);

    expect($review->fresh()?->updated_at?->equalTo($updatedAt))->toBeTrue();
});

it('recounts from the votes table, not from the model it was handed', function (): void {
    $review = Review::factory()->create();
    Reviews::vote($review, Entity::create());

    // A stale copy loaded before the first vote still believes the tallies are zero.
    $stale = Review::query()->findOrFail($review->getKey());
    $stale->helpful_count = 0;
    $stale->syncOriginal();

    Reviews::vote($stale, Entity::create());

    expect($review->fresh()?->helpful_count)->toBe(2)
        ->and($stale->helpful_count)->toBe(2);
});

/*
 * The real-engine proof: while another session holds the review's row lock (a vote in flight),
 * a vote waits on that lock — here it gives up after the lock timeout — before it writes its
 * vote row, so nothing is half-done.
 */
it('waits for the review row lock before writing the vote', function (): void {
    $review = Review::factory()->create();
    $voter = Entity::create();

    config()->set('database.connections.rival', config('database.connections.'.config('database.default')));
    $rival = DB::connection('rival');
    $rival->beginTransaction();
    $rival->table('reviews')->where('id', $review->getKey())->lockForUpdate()->first();

    DriverMatrix::driver() === 'pgsql'
        ? DB::statement("set lock_timeout = '300ms'")
        : DB::statement('set session innodb_lock_wait_timeout = 1');

    try {
        Reviews::vote($review, $voter);
        $this->fail('The vote did not wait for the review row lock.');
    } catch (QueryException $e) {
        expect(strtolower($e->getSql()))->toContain('reviews')->toContain('for update');
    } finally {
        $rival->rollBack();
        DB::purge('rival');
    }

    expect(ReviewVote::query()->count())->toBe(0);
})->skip(fn (): bool => ! in_array(DriverMatrix::driver(), ['pgsql', 'mysql'], true), 'row locks need a real engine');
