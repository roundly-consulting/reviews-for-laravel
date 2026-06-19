<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use RoundlyConsulting\Reviews\Http\Resources\ReviewCollection;
use RoundlyConsulting\Reviews\Http\Resources\ReviewResource;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Tests\Entity;
use RoundlyConsulting\Reviews\Tests\Product;

it('exposes the public review shape', function (): void {
    $product = Product::query()->create();
    $author = Entity::query()->create();

    $review = Review::factory()
        ->approved()
        ->verified()
        ->rating(5)
        ->helpfulVotes(3, 1)
        ->forReviewable($product)
        ->byAuthor($author)
        ->create(['title' => 'Great', 'content' => 'Loved it']);

    $array = (new ReviewResource($review))->toArray(Request::create('/'));

    expect($array)
        ->toMatchArray([
            'id' => $review->id,
            'rating' => 5,
            'status' => 'approved',
            'verified' => true,
            'title' => 'Great',
            'content' => 'Loved it',
            'helpful_count' => 3,
            'unhelpful_count' => 1,
            'helpful_score' => 2,
        ])
        ->and($array['author']['id'])->toBe($author->getKey())
        ->and($array['reviewable']['id'])->toBe($product->getKey())
        ->and($array['approved_at'])->not->toBeNull();
});

it('collects reviews', function (): void {
    Review::factory()->count(2)->create();

    $collection = new ReviewCollection(Review::query()->get());

    expect($collection->collects)->toBe(ReviewResource::class)
        ->and($collection->toArray(Request::create('/')))->toHaveCount(2);
});
