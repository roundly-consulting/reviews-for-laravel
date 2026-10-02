<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Contracts;

use RoundlyConsulting\Reviews\DataTransferObjects\ModerationOutcome;
use RoundlyConsulting\Reviews\Models\Review;

interface ReviewModerator
{
    /**
     * Decide the fate of a review whose rating or text was just written: approve,
     * reject (optionally with a reason), or leave it undecided for manual review.
     *
     * Called with the unsaved model, author and reviewable already associated
     * (`$review->author`, `$review->reviewable`). Two moments reach it: a new
     * review (`$review->exists === false`), and an edit to an existing review's
     * rating, title or content (`$review->exists === true`, `getOriginal()`
     * holds the previous values). Rejected reviews and owner responses are
     * never re-moderated.
     */
    public function moderate(Review $review): ModerationOutcome;
}
