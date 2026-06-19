<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\Enums\ReviewStatus;

it('exposes the three lifecycle cases', function (): void {
    expect(ReviewStatus::Pending->value)->toBe('pending')
        ->and(ReviewStatus::Approved->value)->toBe('approved')
        ->and(ReviewStatus::Rejected->value)->toBe('rejected');
});

it('answers state helpers', function (): void {
    expect(ReviewStatus::Pending->isPending())->toBeTrue()
        ->and(ReviewStatus::Pending->isApproved())->toBeFalse()
        ->and(ReviewStatus::Pending->isRejected())->toBeFalse()
        ->and(ReviewStatus::Approved->isApproved())->toBeTrue()
        ->and(ReviewStatus::Rejected->isRejected())->toBeTrue();
});

it('returns a translated label', function (): void {
    expect(ReviewStatus::Pending->label())->toBe('Pending')
        ->and(ReviewStatus::Approved->label())->toBe('Approved')
        ->and(ReviewStatus::Rejected->label())->toBe('Rejected');
});
