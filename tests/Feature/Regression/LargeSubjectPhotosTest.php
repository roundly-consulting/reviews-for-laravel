<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RoundlyConsulting\Reviews\Enums\ReviewStatus;
use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\Support\ReviewModel;
use RoundlyConsulting\Reviews\Tests\Entity;
use RoundlyConsulting\Reviews\Tests\SideReview;
use RoundlyConsulting\Testing\Database\DriverMatrix;

/*
 * The photo aggregates plucked every approved review id into PHP and bound one placeholder per id.
 * Past the driver's placeholder limit (32 766 on SQLite, 65 535 on PostgreSQL and MySQL) summary(),
 * photoCount() and reviewsWithPhotos() threw for a popular subject. The ids now stay in the
 * database as a subquery.
 */

beforeEach(function (): void {
    Storage::fake('public');
});

function approvedReviewWithPhotos(Entity $subject, int $photos): void
{
    $builder = Reviews::for($subject)->by(Entity::create())->rating(5)->approved();

    for ($i = 0; $i < $photos; $i++) {
        $builder->withPhoto(UploadedFile::fake()->image("p{$i}.jpg", 40, 30));
    }

    $builder->create();
}

/**
 * The bindings of every media query the closure runs.
 *
 * @return list<int>
 */
function mediaQueryBindingCounts(callable $callback): array
{
    $counts = [];

    DB::listen(function (QueryExecuted $query) use (&$counts): void {
        if (str_contains(str_replace(['"', '`'], '', strtolower($query->sql)), 'from media')) {
            $counts[] = count($query->bindings);
        }
    });

    $callback();

    return $counts;
}

it('binds the same number of values whatever the number of approved reviews', function (): void {
    $small = Entity::create();
    $large = Entity::create();

    approvedReviewWithPhotos($small, 1);

    for ($i = 0; $i < 12; $i++) {
        approvedReviewWithPhotos($large, $i === 0 ? 1 : 0);
    }

    $smallBindings = mediaQueryBindingCounts(fn () => Reviews::for($small)->summary());
    $largeBindings = mediaQueryBindingCounts(fn () => Reviews::for($large)->summary());

    expect($smallBindings)->toHaveCount(2)
        ->and($largeBindings)->toBe($smallBindings)
        ->and(Reviews::for($large)->summary()->photoCount)->toBe(1);
});

it('summarises a subject with more approved reviews than the driver has placeholders', function (): void {
    $subject = Entity::create();

    approvedReviewWithPhotos($subject, 2);
    approvedReviewWithPhotos($subject, 1);

    if (DriverMatrix::driver() === 'mysql') {
        DB::statement('set session cte_max_recursion_depth = 100000');
    }

    DB::insert(
        'insert into '.ReviewModel::table().' (reviewable_type, reviewable_id, status, rating) '
        .'with recursive seq(n) as (select 1 union all select n + 1 from seq where n < 65534) '
        .'select ?, ?, ?, 4 from seq',
        [$subject->getMorphClass(), $subject->getKey(), ReviewStatus::Approved->value],
    );

    $summary = Reviews::for($subject)->summary();

    expect($summary->count)->toBe(65536)
        ->and($summary->photoCount)->toBe(3)
        ->and($summary->reviewsWithPhotos)->toBe(2)
        ->and(Reviews::for($subject)->photoCount())->toBe(3)
        ->and(Reviews::for($subject)->reviewsWithPhotos())->toBe(2);
});

it('still counts photos when the review model lives on another connection', function (): void {
    SideReview::createSideConnection();
    config()->set('reviews.model', SideReview::class);

    try {
        $subject = Entity::create();

        approvedReviewWithPhotos($subject, 2);
        approvedReviewWithPhotos($subject, 0);

        expect(SideReview::query()->count())->toBe(2)
            ->and(Reviews::for($subject)->photoCount())->toBe(2)
            ->and(Reviews::for($subject)->reviewsWithPhotos())->toBe(1);
    } finally {
        SideReview::dropSideConnection();
    }
});
