<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\DataTransferObjects\RatingSummary;

it('holds the aggregate values', function (): void {
    $summary = new RatingSummary(average: 4.5, count: 2, distribution: [5 => 1, 4 => 1]);

    expect($summary->average)->toBe(4.5)
        ->and($summary->count)->toBe(2)
        ->and($summary->distribution)->toBe([5 => 1, 4 => 1]);
});

it('serialises to an array', function (): void {
    $summary = new RatingSummary(average: null, count: 0, distribution: []);

    expect($summary->toArray())->toBe([
        'average' => null,
        'count' => 0,
        'distribution' => [],
    ]);
});
