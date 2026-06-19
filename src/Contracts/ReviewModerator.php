<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Contracts;

use RoundlyConsulting\Reviews\DataTransferObjects\ModerationOutcome;
use RoundlyConsulting\Reviews\Models\Review;

interface ReviewModerator
{
    /**
     * Inspect a freshly built (unsaved) review and decide its fate: approve,
     * reject (optionally with a reason), or leave pending for manual review.
     */
    public function moderate(Review $review): ModerationOutcome;
}
