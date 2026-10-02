<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Support\ReviewModel;

/*
 * The two ordering scopes compile differently per engine, so each is pinned twice: the SQL a
 * given grammar emits (checkable without that engine running — Laravel connects lazily), and
 * the order the active engine actually returns (the pgsql / mysql CI legs run it for real).
 */

function orderingSqlOn(string $connection, callable $scope): string
{
    $query = ReviewModel::new()->setConnection($connection)->newQuery();

    $scope($query);

    return $query->toSql();
}

it('orders net-negative reviews below neutral ones', function (): void {
    $negative = Review::factory()->helpfulVotes(1, 4)->create();
    $neutral = Review::factory()->helpfulVotes(0, 0)->create();
    $positive = Review::factory()->helpfulVotes(3, 0)->create();

    expect(Review::query()->mostHelpful()->pluck('id')->all())
        ->toBe([$positive->id, $neutral->id, $negative->id]);
});

it('casts the unsigned tallies to signed before subtracting on mysql-family engines', function (string $connection): void {
    // Both tally columns are UNSIGNED. MySQL/MariaDB raise ERROR 1690 ("BIGINT UNSIGNED value is
    // out of range") the moment `helpful_count - unhelpful_count` would go negative, unless the
    // server runs with NO_UNSIGNED_SUBTRACTION — which Laravel's default sql_mode does not set.
    expect(orderingSqlOn($connection, fn ($query) => $query->mostHelpful()))
        ->toContain('(cast(`reviews`.`helpful_count` as signed) - cast(`reviews`.`unhelpful_count` as signed)) desc');
})->with(['mysql', 'mariadb']);

it('subtracts the tallies directly where the engine has no unsigned integers', function (): void {
    expect(orderingSqlOn('pgsql', fn ($query) => $query->mostHelpful()))
        ->toContain('("reviews"."helpful_count" - "reviews"."unhelpful_count") desc');
});

it('lists never-approved reviews after approved ones', function (): void {
    // pgsql sorts NULLs first under DESC, sqlite and mysql last: without an explicit NULL rank a
    // mixed-status `latestFirst()` put pending reviews on top on postgres only.
    $older = Review::factory()->approved()->create(['approved_at' => now()->subDays(2), 'created_at' => now()->subDays(3)]);
    $newer = Review::factory()->approved()->create(['approved_at' => now()->subDay(), 'created_at' => now()->subDays(2)]);
    $pending = Review::factory()->pending()->create(['created_at' => now()]);
    $olderPending = Review::factory()->pending()->create(['created_at' => now()->subHour()]);

    expect(Review::query()->latestFirst()->pluck('id')->all())
        ->toBe([$newer->id, $older->id, $pending->id, $olderPending->id]);
});

it('ranks NULL approval stamps last in the SQL on every grammar', function (string $connection, string $approvedAt): void {
    expect(orderingSqlOn($connection, fn ($query) => $query->latestFirst()))
        ->toContain("order by case when {$approvedAt} is null then 1 else 0 end asc, {$approvedAt} desc");
})->with([
    'sqlite' => ['sqlite', '"reviews"."approved_at"'],
    'pgsql' => ['pgsql', '"reviews"."approved_at"'],
    'mysql' => ['mysql', '`reviews`.`approved_at`'],
]);
