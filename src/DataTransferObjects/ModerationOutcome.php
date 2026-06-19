<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\DataTransferObjects;

use RoundlyConsulting\Reviews\Enums\ModerationDecision;

/**
 * The result of inspecting a pending review: a decision plus an optional reason
 * (used when rejecting).
 */
final readonly class ModerationOutcome
{
    public function __construct(
        public ModerationDecision $decision,
        public ?string $reason = null,
    ) {}

    public static function approve(): self
    {
        return new self(ModerationDecision::Approve);
    }

    public static function reject(?string $reason = null): self
    {
        return new self(ModerationDecision::Reject, $reason);
    }

    public static function pending(): self
    {
        return new self(ModerationDecision::Pending);
    }
}
