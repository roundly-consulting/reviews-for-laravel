<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Tests\Entity;

it('separates top-level reviews from responses', function (): void {
    $parent = Review::factory()->approved()->create();
    $parent->respond(Entity::query()->create(), 'A response');

    expect(Review::query()->topLevel()->count())->toBe(1)
        ->and(Review::query()->responses()->count())->toBe(1);
});

it('navigates parent and responses relations', function (): void {
    $parent = Review::factory()->approved()->create();
    $response = $parent->respond(Entity::query()->create(), 'Reply');

    expect($response->parent->is($parent))->toBeTrue()
        ->and($parent->responses()->first()->is($response))->toBeTrue();
});
