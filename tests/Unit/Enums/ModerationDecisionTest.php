<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\Enums\ModerationDecision;

it('exposes the decision cases', function (): void {
    expect(ModerationDecision::Approve->value)->toBe('approve')
        ->and(ModerationDecision::Reject->value)->toBe('reject')
        ->and(ModerationDecision::Pending->value)->toBe('pending');
});

it('answers decision helpers', function (): void {
    expect(ModerationDecision::Approve->isApprove())->toBeTrue()
        ->and(ModerationDecision::Reject->isReject())->toBeTrue()
        ->and(ModerationDecision::Pending->isPending())->toBeTrue()
        ->and(ModerationDecision::Approve->isReject())->toBeFalse();
});

it('adopts the enums trait helpers', function (): void {
    expect(ModerationDecision::values()->all())->toBe(['approve', 'reject', 'pending'])
        ->and(ModerationDecision::labels()->all())->toBe(['Approve', 'Reject', 'Pending'])
        ->and(ModerationDecision::Approve->readable())->toBe('Approve')
        ->and(ModerationDecision::validationRule())->toBe('in:approve,reject,pending')
        ->and(ModerationDecision::toOptions()->all())->toBe([
            'approve' => 'Approve',
            'reject' => 'Reject',
            'pending' => 'Pending',
        ]);
});
