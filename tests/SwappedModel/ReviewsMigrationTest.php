<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Reviews\Tests\OwnTableReview;
use RoundlyConsulting\Reviews\Tests\TenantReview;

/*
 * `0001_create_reviews_table` always created a literal `reviews` table and pointed `parent_id` at
 * it, while `0002` already followed `reviews.model`. A host on its own table got an unused
 * `reviews` table — or "table already exists" when it owned one. The migration now creates the
 * configured model's table, self-referenced, and leaves a table that already exists alone (the
 * host created it, or a republished copy runs over an earlier install).
 */

function reviewsMigration(): Migration
{
    return require __DIR__.'/../../database/migrations/0001_create_reviews_table.php';
}

it('creates no reviews table when the configured model lives on a table the host made', function (): void {
    expect(Schema::hasTable('tenant_reviews'))->toBeTrue()
        ->and(Schema::hasTable('reviews'))->toBeFalse();
});

it('creates the configured table, its parent_id referencing that same table', function (): void {
    config()->set('reviews.model', OwnTableReview::class);

    reviewsMigration()->up();

    $parent = collect(Schema::getForeignKeys('own_reviews'))
        ->firstWhere(fn (array $key): bool => $key['columns'] === ['parent_id']);

    expect(Schema::hasTable('own_reviews'))->toBeTrue()
        ->and($parent)->not->toBeNull()
        ->and($parent['foreign_table'])->toBe('own_reviews');
});

it('leaves an existing configured table alone when run again', function (): void {
    $kept = TenantReview::factory()->create();

    reviewsMigration()->up();

    expect(TenantReview::query()->whereKey($kept->getKey())->exists())->toBeTrue()
        ->and(Schema::hasTable('reviews'))->toBeFalse();
});
