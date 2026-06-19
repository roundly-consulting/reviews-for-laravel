<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\DataTransferObjects\CreateReviewData;
use RoundlyConsulting\Reviews\Tests\Entity;

it('holds the review attributes', function (): void {
    $data = new CreateReviewData(
        author: $author = new Entity,
        reviewable: $reviewable = new Entity,
        content: 'Great!',
        title: 'Title',
        rating: 5,
        meta: collect(['k' => 'v']),
        approved: true,
    );

    expect($data->author)->toBe($author)
        ->and($data->reviewable)->toBe($reviewable)
        ->and($data->content)->toBe('Great!')
        ->and($data->title)->toBe('Title')
        ->and($data->rating)->toBe(5)
        ->and($data->meta?->all())->toBe(['k' => 'v'])
        ->and($data->approved)->toBeTrue();
});

it('defaults optional fields', function (): void {
    $data = new CreateReviewData(
        author: new Entity,
        reviewable: new Entity,
    );

    expect($data->content)->toBeNull()
        ->and($data->title)->toBeNull()
        ->and($data->rating)->toBeNull()
        ->and($data->meta)->toBeNull()
        ->and($data->approved)->toBeFalse();
});
