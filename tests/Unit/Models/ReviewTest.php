<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Tests\Entity;

it('has a relationship to the reviewable', function (): void {
    $reviewable = Entity::create();

    $review = new Review;
    $review->reviewable()->associate($reviewable);
    $review->content = 'Testing';
    $review->save();

    expect($review)
        ->exists->toBeTrue()
        ->reviewable_type->toBe(Entity::class)
        ->reviewable_id->toBe($reviewable->id)
        ->reviewable->is($reviewable)->toBeTrue();
});

it('has a relationship to the author', function (): void {
    $author = Entity::create();

    $review = new Review;
    $review->author()->associate($author);
    $review->content = 'Testing';
    $review->save();

    expect($review)
        ->exists->toBeTrue()
        ->author_type->toBe(Entity::class)
        ->author_id->toBe($author->id)
        ->author->is($author)->toBeTrue();
});

it('casts meta to a collection', function (): void {
    $review = new Review;
    $review->content = 'Testing';
    $review->meta = collect(['rating' => 5]);
    $review->save();

    expect($review->fresh()->meta)
        ->toBeInstanceOf(Collection::class)
        ->all()->toBe(['rating' => 5]);
});
