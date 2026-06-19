<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Tests\Entity;

it('creates a review using the factory', function (): void {
    $author = Entity::create();
    $reviewable = Entity::create();

    $review = Review::factory()
        ->for($author, 'author')
        ->for($reviewable, 'reviewable')
        ->create();

    expect($review)
        ->title->not->toBeEmpty()
        ->content->not->toBeEmpty();

    $this->assertDatabaseHas('reviews', [
        'author_id' => $author->getKey(),
        'author_type' => $author->getMorphClass(),
        'reviewable_id' => $reviewable->getKey(),
        'reviewable_type' => $reviewable->getMorphClass(),
        'title' => $review->title,
        'content' => $review->content,
    ]);
});
