<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Actions;

use RoundlyConsulting\PackageToolkit\Support\Config;
use RoundlyConsulting\Reviews\DataTransferObjects\ModerationOutcome;
use RoundlyConsulting\Reviews\DataTransferObjects\UpdateReviewData;
use RoundlyConsulting\Reviews\Events\ReviewApproved;
use RoundlyConsulting\Reviews\Events\ReviewRejected;
use RoundlyConsulting\Reviews\Events\ReviewUpdated;
use RoundlyConsulting\Reviews\Models\Review;

/**
 * Applies the provided fields of an edit (null = leave unchanged).
 *
 * An edit that really changes the rating, title or content goes back through the same
 * moderation pipeline a new review does ({@see ModerateReview}): `auto_approve`, then the
 * configured moderator. With `reviews.reset_status_on_edit` on, an undecided outcome sends the
 * review to `reviews.default_status` (pending by default) for a human to look at again; with it
 * off, an undecided outcome keeps the current status — but the moderator still runs, so a banned
 * word cannot be edited into a live review either way.
 *
 * Two kinds of review are never re-moderated: a **rejected** review stays rejected (with its
 * reason) until someone approves it explicitly, and an owner **response** stays approved — it
 * never enters the moderation queue.
 */
final readonly class UpdateReview
{
    public function __construct(
        private ValidatesRating $validateRating,
        private ModerateReview $moderate,
    ) {}

    public function execute(Review $review, UpdateReviewData $data): Review
    {
        $before = $review->status;

        if ($data->title !== null) {
            $review->title = $data->title;
        }

        if ($data->content !== null) {
            $review->content = $data->content;
        }

        if ($data->rating !== null) {
            $this->validateRating->execute($data->rating);
            $review->rating = $data->rating;
        }

        if ($data->meta !== null) {
            $review->meta = $data->meta;
        }

        if ($data->verified !== null) {
            $review->verified = $data->verified;
        }

        $outcome = $this->remoderate($review);

        $review->save();

        ReviewUpdated::dispatch($review);

        if ($outcome !== null && $review->status !== $before) {
            if ($review->isApproved()) {
                ReviewApproved::dispatch($review);
            } elseif ($review->isRejected()) {
                ReviewRejected::dispatch($review, $outcome->reason);
            }
        }

        return $review;
    }

    private function remoderate(Review $review): ?ModerationOutcome
    {
        if ($review->isResponse() || $review->isRejected()) {
            return null;
        }

        if (! $review->isDirty(['rating', 'title', 'content'])) {
            return null;
        }

        return $this->moderate->execute(
            $review,
            approve: Config::boolean('reviews.auto_approve'),
            undecided: Config::boolean('reviews.reset_status_on_edit', true) ? null : $review->status,
        );
    }
}
