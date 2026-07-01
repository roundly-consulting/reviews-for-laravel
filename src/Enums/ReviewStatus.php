<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Enums;

use RoundlyConsulting\Enums\Helpers;

enum ReviewStatus: string
{
    use Helpers;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    public function isApproved(): bool
    {
        return $this === self::Approved;
    }

    public function isRejected(): bool
    {
        return $this === self::Rejected;
    }
}
