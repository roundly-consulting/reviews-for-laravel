<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\Enums\ReviewStatus;
use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Support\PendingReview;
use RoundlyConsulting\Reviews\Tests\Entity;

it('creates a review through the full fluent chain', function (): void {
    $reviewable = Entity::create();
    $author = Entity::create();

    $review = Reviews::for($reviewable)
        ->by($author)
        ->rating(5)
        ->title('Loved it')
        ->content('Great place')
        ->meta(['visit' => 'dinner'])
        ->create();

    expect($review)->toBeInstanceOf(Review::class)
        ->rating->toBe(5)
        ->title->toBe('Loved it')
        ->content->toBe('Great place')
        ->and($review->meta?->get('visit'))->toBe('dinner')
        ->and($review->reviewable->is($reviewable))->toBeTrue()
        ->and($review->author->is($author))->toBeTrue();
});

it('creates a rating-only review', function (): void {
    $review = Reviews::for(Entity::create())->by(Entity::create())->rating(4)->create();

    expect($review->rating)->toBe(4)
        ->and($review->content)->toBeNull();
});

it('force-approves with the approved shortcut', function (): void {
    $review = Reviews::for(Entity::create())
        ->by(Entity::create())
        ->content('Trusted')
        ->approved()
        ->create();

    expect($review->status)->toBe(ReviewStatus::Approved);
});

it('accepts a collection for meta', function (): void {
    $review = Reviews::for(Entity::create())
        ->by(Entity::create())
        ->content('x')
        ->meta(collect(['a' => 'b']))
        ->create();

    expect($review->meta?->get('a'))->toBe('b');
});

it('fixes the subject and author when the builder is made', function (): void {
    $reviewable = Entity::create();
    $author = Entity::create();

    $pending = Reviews::for($reviewable)->by($author);

    expect($pending)->toBeInstanceOf(PendingReview::class);

    $review = $pending->rating(3)->create();

    expect($review->reviewable->is($reviewable))->toBeTrue()
        ->and($review->author->is($author))->toBeTrue();
});
