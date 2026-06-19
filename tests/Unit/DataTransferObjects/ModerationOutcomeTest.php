<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\DataTransferObjects\ModerationOutcome;
use RoundlyConsulting\Reviews\Enums\ModerationDecision;

it('builds approve, reject, and pending outcomes', function (): void {
    expect(ModerationOutcome::approve()->decision)->toBe(ModerationDecision::Approve)
        ->and(ModerationOutcome::pending()->decision)->toBe(ModerationDecision::Pending)
        ->and(ModerationOutcome::reject('bad')->reason)->toBe('bad');
});

it('answers decision helpers', function (): void {
    expect(ModerationDecision::Approve->isApprove())->toBeTrue()
        ->and(ModerationDecision::Reject->isReject())->toBeTrue()
        ->and(ModerationDecision::Pending->isPending())->toBeTrue()
        ->and(ModerationDecision::Approve->isReject())->toBeFalse();
});
