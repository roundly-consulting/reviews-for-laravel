<?php

declare(strict_types=1);

use RoundlyConsulting\PackageToolkit\Enums\DatabaseDriver;
use RoundlyConsulting\Reviews\Enums\ReviewStatus;
use RoundlyConsulting\Reviews\ReviewsManager;
use RoundlyConsulting\Reviews\ReviewsServiceProvider;
use RoundlyConsulting\Reviews\Tests\Member;
use RoundlyConsulting\Reviews\Tests\Product;
use RoundlyConsulting\Reviews\Tests\Voter;
use RoundlyConsulting\Testing\Database\DriverMatrix;

/**
 * M + P + R for the two review tables.
 *
 * This file replaces ~200 lines of hand-rolled reinvention: the suite carried its own
 * migration globber, its own `Schema::create` regex scraper, its own four-form FK-edge
 * walker, its own ALTER placement check and its own publish-and-migrate case. The ideas
 * were right — it even pinned the edge count at 2 so the parse could not go vacuous, and it
 * invented `TABLE_RESOLVERS` independently. That is exactly why it should be the shared
 * implementation rather than this package's copy of it.
 *
 * Its publish-and-migrate case also ran against a throwaway **SQLite** file, which is the
 * engine that cannot fail an ordering check: it happily creates a table whose foreign key
 * names a missing parent and only complains at insert time. That is how five packages
 * shipped uninstallable migration orders under green suites — and how this package shipped
 * one (`create_review_votes_table` sorted before `create_reviews_table`; a fresh install
 * died on Postgres with `relation "reviews" does not exist`).
 */
$migrations = __DIR__.'/../../database/migrations';

/**
 * The non-literal `->constrained()` argument, mapped to the table it resolves to under the
 * packaged default config. `0002_create_review_votes_table` resolves its parent through
 * `ReviewModel::table()` so a swapped `reviews.model` is honoured (the fix in 0a23fa8); the
 * parse still has to know what that lands on. The resolver **never guesses on a non-literal**
 * — an unmapped expression FAILS rather than silently dropping the edge, which is what keeps
 * `foreignKeys: 2` honest instead of a number that passes over an empty parse.
 */
$tableResolvers = [
    'ReviewModel::table()' => 'reviews',
];

/**
 * M — the structural, engine-independent order pin.
 *
 * Publish order IS run order (directory sort), so a migration that constrains onto a table
 * an earlier one has not created yet is uninstallable in a host.
 *
 * `foreignKeys: 2` pins the edge count: `reviews.parent_id` → reviews (the self-referential
 * owner-response link) and `review_votes.review_id` → the configured review table. The
 * `reviewable`, `author` and `voter` columns are deliberately unconstrained morphs — a
 * subject or an author can live in any table.
 */
it('has a runnable migration order', function () use ($migrations, $tableResolvers): void {
    expect($migrations)->toHaveRunnableMigrationOrder(
        foreignKeys: 2,
        tableResolvers: $tableResolvers,
    );
});

/**
 * P — the publish-only guards. The fleet publishes migrations timestamped rather than
 * auto-loading them; doing both runs both copies and dies on a duplicate table (bug #5, on
 * three packages). `count: 2` pins the file count so neither check can pass over an empty or
 * relocated directory.
 *
 * The bespoke publish cases in tests/Feature/Provider/PublishOnlyMigrationsTest.php are
 * kept: the aggregate stub's separate opt-in tag and the "every scripted tag still exists"
 * pin have no preset equivalent.
 */
it('never auto-loads its migrations — the host publishes them', function (): void {
    expect(ReviewsServiceProvider::class)->toNotAutoLoadMigrations();
});

it('publishes every migration timestamp-injected into the host', function (): void {
    expect(ReviewsServiceProvider::class)->toPublishMigrationsTimestamped('reviews-migrations', 2);
});

/**
 * R — the real-engine proof. The deleted local version ran the published files against a
 * throwaway SQLite database, the one engine that cannot fail this class of check.
 * `migrations: 2` pins the count, and the expectation additionally fails a set that "applies
 * cleanly" while creating no tables — an empty `up()` otherwise passes and proves nothing.
 */
it('applies its migrations on postgres', function () use ($migrations): void {
    expect($migrations)->toApplyOnConnection('pgsql', migrations: 2);
})->skip(fn (): bool => ! test()->connectionAvailable('pgsql'), 'no postgres connection available');

/**
 * The negative control, adoptable here where it was not for requests or credits: this
 * package has real FK edges, so a reversed order gives Postgres something to refuse. A green
 * FK test proves nothing until you have watched the engine actually reject the broken order
 * (forms #28). This fails loudly if the engine ACCEPTS the reordered set, which is what makes
 * the positive half above meaningful — and it is the shape this package genuinely shipped.
 */
it('rejects a child-before-parent order on postgres', function () use ($migrations): void {
    expect($migrations)->toRejectBrokenOrderOnConnection(
        fn (array $files): array => array_reverse($files),
        'pgsql',
    );
})->skip(fn (): bool => ! test()->connectionAvailable('pgsql'), 'no postgres connection available');

/**
 * The driver-truth pin: compares the env-declared driver against what the connection itself
 * answers, so a leg that exports the location vars but not `TESTING_DB_DRIVER` (or a TestCase
 * that decapitates the base case by overriding `defineEnvironment()` without `parent::`) reds
 * instead of quietly running sqlite and reporting green as a "postgres" job. Strictly stronger
 * than reading a skip count by hand.
 */
it('runs on the driver the leg declares', function (): void {
    expect(DatabaseDriver::current())->toBe(DatabaseDriver::from(DriverMatrix::driver()));
});

/**
 * The `meta` jsonb column, the enum-backed `status` and the unsigned tally columns are what
 * the drivers render differently — `json` has no equality operator on Postgres at all.
 * Pinning a round-trip on whatever engine the leg configured proves the columns are usable
 * rather than merely creatable.
 */
it('round-trips a review and its vote on the configured engine', function (): void {
    $review = Product::create()
        ->addReview(Member::create())
        ->rating(4)
        ->title('Solid')
        ->content('Good value.')
        ->meta(['tier' => 2, 'region' => 'eu'])
        ->create();

    app(ReviewsManager::class)->vote($review, Voter::create());

    $fresh = $review->fresh();

    expect($fresh?->status)->toBe(ReviewStatus::Pending)
        ->and($fresh?->rating)->toBe(4)
        ->and($fresh?->title)->toBe('Solid')
        ->and($fresh?->helpful_count)->toBe(1)
        // Key-by-key rather than `toBe` on the whole map: jsonb sorts object keys (by
        // length, then bytewise), so `['tier' => 2, 'region' => 'eu']` comes back reordered
        // and a whole-map `toBe` (`===`, order-sensitive) would red on Postgres while
        // passing on sqlite. `toEqual` would hide the opposite bug — it is `==`, so it would
        // accept the string "2" for the int 2, which is what a round-trip pin exists to catch.
        ->and($fresh?->meta['tier'] ?? null)->toBe(2)
        ->and($fresh?->meta['region'] ?? null)->toBe('eu');
});
