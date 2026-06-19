<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Moderation;

use RoundlyConsulting\Reviews\Contracts\ReviewModerator;
use RoundlyConsulting\Reviews\DataTransferObjects\ModerationOutcome;
use RoundlyConsulting\Reviews\Models\Review;

/**
 * The default no-op moderator: every review is left to the configured default
 * status (pending unless auto-approve is on).
 */
final class NullModerator implements ReviewModerator
{
    public function moderate(Review $review): ModerationOutcome
    {
        return ModerationOutcome::pending();
    }
}
