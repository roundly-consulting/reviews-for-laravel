<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Enums;

enum ModerationDecision: string
{
    case Approve = 'approve';
    case Reject = 'reject';
    case Pending = 'pending';

    public function isApprove(): bool
    {
        return $this === self::Approve;
    }

    public function isReject(): bool
    {
        return $this === self::Reject;
    }

    public function isPending(): bool
    {
        return $this === self::Pending;
    }
}
