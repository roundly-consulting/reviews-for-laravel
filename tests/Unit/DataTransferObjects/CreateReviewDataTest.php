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
        meta: collect(['k' => 'v']),
    );

    expect($data->author)->toBe($author)
        ->and($data->reviewable)->toBe($reviewable)
        ->and($data->content)->toBe('Great!')
        ->and($data->title)->toBe('Title')
        ->and($data->meta?->all())->toBe(['k' => 'v']);
});

it('defaults title and meta to null', function (): void {
    $data = new CreateReviewData(
        author: new Entity,
        reviewable: new Entity,
        content: 'Great!',
    );

    expect($data->title)->toBeNull()
        ->and($data->meta)->toBeNull();
});
