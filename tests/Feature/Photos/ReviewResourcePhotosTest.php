<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\Http\Resources\ReviewResource;
use RoundlyConsulting\Reviews\Tests\Entity;

beforeEach(function (): void {
    Storage::fake('public');
});

it('includes a photos array when the review has photos', function (): void {
    $review = Reviews::for(Entity::create())
        ->by(Entity::create())
        ->rating(5)
        ->content('great')
        ->withPhoto(UploadedFile::fake()->image('a.jpg', 400, 300))
        ->create();

    $payload = (new ReviewResource($review))->toArray(Request::create('/'));

    expect($payload['photos'])->toHaveCount(1)
        ->and($payload['photos'][0])->toHaveKeys(['id', 'url', 'srcset'])
        ->and($payload['photos'][0]['url'])->not->toBe('');
});

it('returns an empty photos array when the review has none', function (): void {
    $review = Reviews::for(Entity::create())->by(Entity::create())->rating(5)->content('great')->create();

    $payload = (new ReviewResource($review))->toArray(Request::create('/'));

    expect($payload['photos'])->toBe([]);
});

it('returns an empty photos array when photos are disabled', function (): void {
    $review = Reviews::for(Entity::create())->by(Entity::create())->rating(5)->content('great')->create();

    config()->set('reviews.photos.enabled', false);

    $payload = (new ReviewResource($review))->toArray(Request::create('/'));

    expect($payload['photos'])->toBe([]);
});
