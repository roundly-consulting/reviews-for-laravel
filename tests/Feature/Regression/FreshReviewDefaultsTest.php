<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Reviews\Enums\ReviewStatus;
use RoundlyConsulting\Reviews\Events\ReviewVerified;
use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\Http\Resources\ReviewResource;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Tests\Entity;

/*
 * The column defaults (status pending, verified false, zero tallies) live in the migration, and
 * Eloquent never reads them back after an insert. A just-created review or response used to carry
 * nulls in memory until refreshed: its JSON said `helpful_count: null`, and `unverify()` on a fresh
 * response saw `null !== false`, wrote the row and fired a spurious ReviewVerified — and under the
 * fake it threw a TypeError.
 */

it('serialises a just-created review and response with the column defaults', function (): void {
    $review = Reviews::for(Entity::create())->by(Entity::create())->rating(5)->create();
    $response = Reviews::respond($review, Entity::create(), 'Thanks!');

    foreach ([$review, $response] as $fresh) {
        $data = (new ReviewResource($fresh))->resolve();

        expect($data['helpful_count'])->toBe(0)
            ->and($data['unhelpful_count'])->toBe(0)
            ->and($data['helpful_score'])->toBe(0)
            ->and($data['verified'])->toBeFalse();
    }
});

it('starts a new model with the column defaults in memory', function (): void {
    $review = new Review;

    expect($review->status)->toBe(ReviewStatus::Pending)
        ->and($review->verified)->toBeFalse()
        ->and($review->helpful_count)->toBe(0)
        ->and($review->unhelpful_count)->toBe(0);
});

it('fires no ReviewVerified when unverifying a fresh response', function (): void {
    $review = Reviews::for(Entity::create())->by(Entity::create())->rating(5)->create();
    $response = Reviews::respond($review, Entity::create(), 'Thanks!');

    Event::fake([ReviewVerified::class]);

    Reviews::unverify($response);

    Event::assertNotDispatched(ReviewVerified::class);
});

it('records nothing when unverifying a fresh response under the fake', function (): void {
    $fake = Reviews::fake();

    $review = Reviews::for(Entity::create())->by(Entity::create())->rating(5)->create();
    $response = Reviews::respond($review, Entity::create(), 'Thanks!');

    Reviews::unverify($response);

    $fake->assertNothingUnverified();
});
