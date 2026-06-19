<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\DataTransferObjects\UpdateReviewData;

it('defaults every field to null meaning unchanged', function (): void {
    $data = new UpdateReviewData;

    expect($data->title)->toBeNull()
        ->and($data->content)->toBeNull()
        ->and($data->rating)->toBeNull()
        ->and($data->meta)->toBeNull();
});

it('holds the provided changes', function (): void {
    $data = new UpdateReviewData(
        title: 'Updated',
        content: 'New content',
        rating: 4,
        meta: collect(['edited' => true]),
    );

    expect($data->title)->toBe('Updated')
        ->and($data->content)->toBe('New content')
        ->and($data->rating)->toBe(4)
        ->and($data->meta?->all())->toBe(['edited' => true]);
});
