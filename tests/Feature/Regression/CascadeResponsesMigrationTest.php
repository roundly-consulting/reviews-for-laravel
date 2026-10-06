<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Support\ReviewModel;
use RoundlyConsulting\Reviews\Tests\Member;
use RoundlyConsulting\Reviews\Tests\Product;

/*
 * The force-delete hook covers every model-level path. A delete that bypasses Eloquent (a query
 * builder delete, a host's own SQL) still met `nullOnDelete()` on `parent_id` and promoted the
 * responses to top-level reviews. A publish-only migration switches the key to cascade on the
 * configured table — on an install that already ran 0001 too, without losing a row.
 */

const CASCADE_MIGRATION = __DIR__.'/../../../database/migrations/0003_cascade_review_responses_on_delete.php';

/** @return array{string, string, string, string, string, string, string}|null */
function parentKey(): ?array
{
    return collect(Schema::getForeignKeys(ReviewModel::table()))
        ->first(fn (array $key): bool => $key['columns'] === ['parent_id']);
}

it('cascades a database-level delete of a review to its responses', function (): void {
    $review = Reviews::for(Product::create())->by(Member::create())->rating(4)->approved()->create();
    Reviews::respond($review, Member::create(), 'Thanks!');

    Review::query()->toBase()->where('id', $review->getKey())->delete();

    expect(DB::table(ReviewModel::table())->count())->toBe(0)
        ->and(parentKey()['on_delete'] ?? null)->toBe('cascade');
});

it('switches an install that already ran 0001 to cascade, keeping every row', function (): void {
    // The key as 0001 shipped it.
    Schema::table(ReviewModel::table(), function (Blueprint $table): void {
        $table->dropForeign(['parent_id']);
        $table->foreign('parent_id')->references('id')->on(ReviewModel::table())->nullOnDelete();
    });

    expect(parentKey()['on_delete'] ?? null)->toBe('set null');

    $review = Reviews::for(Product::create())->by(Member::create())->rating(4)->approved()->create();
    $response = Reviews::respond($review, Member::create(), 'Thanks!');
    Reviews::vote($review, Member::create());

    // Published under a host timestamp and run by the real migrator, as `php artisan migrate` would.
    $directory = sys_get_temp_dir().'/reviews-cascade-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($directory);
    File::copy(CASCADE_MIGRATION, $directory.'/2026_10_06_000000_cascade_review_responses_on_delete.php');

    try {
        $this->artisan('migrate', ['--path' => $directory, '--realpath' => true])->assertSuccessful();
    } finally {
        File::deleteDirectory($directory);
    }

    expect(parentKey()['on_delete'] ?? null)->toBe('cascade')
        ->and(DB::table(ReviewModel::table())->count())->toBe(2)
        ->and(DB::table('review_votes')->count())->toBe(1)
        ->and(Review::query()->find($response->getKey())?->parent_id)->toBe($review->getKey());

    Review::query()->toBase()->where('id', $review->getKey())->delete();

    expect(DB::table(ReviewModel::table())->count())->toBe(0)
        ->and(DB::table('review_votes')->count())->toBe(0);
});

it('leaves a key that already cascades alone', function (): void {
    $migration = require CASCADE_MIGRATION;

    $migration->up();
    $migration->up();

    expect(parentKey()['on_delete'] ?? null)->toBe('cascade');
});
