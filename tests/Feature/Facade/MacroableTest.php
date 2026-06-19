<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\Reviews as ReviewsManager;

afterEach(function (): void {
    ReviewsManager::flushMacros();
});

it('resolves a registered macro through the facade', function (): void {
    Reviews::macro('greeting', fn (): string => 'hello from reviews');

    expect(Reviews::greeting())->toBe('hello from reviews');
});

it('resolves a macro on the manager instance', function (): void {
    ReviewsManager::macro('double', fn (int $n): int => $n * 2);

    expect(app(ReviewsManager::class)->double(21))->toBe(42);
});
