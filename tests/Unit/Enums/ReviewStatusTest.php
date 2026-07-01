<?php

declare(strict_types=1);

use RoundlyConsulting\Enums\DataTransferObjects\EnumOption;
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

it('returns a readable label from the enums trait', function (): void {
    expect(ReviewStatus::Pending->readable())->toBe('Pending')
        ->and(ReviewStatus::Approved->readable())->toBe('Approved')
        ->and(ReviewStatus::Rejected->readable())->toBe('Rejected')
        ->and(ReviewStatus::Pending->label())->toBe('Pending');
});

it('lists values and labels via the trait', function (): void {
    expect(ReviewStatus::values()->all())->toBe(['pending', 'approved', 'rejected'])
        ->and(ReviewStatus::names()->all())->toBe(['Pending', 'Approved', 'Rejected'])
        ->and(ReviewStatus::labels()->all())->toBe(['Pending', 'Approved', 'Rejected']);
});

it('builds select options via the trait', function (): void {
    expect(ReviewStatus::toOptions()->all())->toBe([
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ]);

    $options = ReviewStatus::options();

    expect($options)->toHaveCount(3)
        ->and($options->first())->toBeInstanceOf(EnumOption::class)
        ->and($options->first()->value)->toBe('pending')
        ->and($options->first()->label)->toBe('Pending');
});

it('builds a validation rule from the backed values', function (): void {
    expect(ReviewStatus::validationRule())->toBe('in:pending,approved,rejected');
});

it('resolves cases by name and label', function (): void {
    expect(ReviewStatus::fromName('Approved'))->toBe(ReviewStatus::Approved)
        ->and(ReviewStatus::tryFromLabel('Rejected'))->toBe(ReviewStatus::Rejected)
        ->and(ReviewStatus::tryFromName('Nope'))->toBeNull()
        ->and(ReviewStatus::hasValue('pending'))->toBeTrue()
        ->and(ReviewStatus::hasValue('missing'))->toBeFalse();
});
