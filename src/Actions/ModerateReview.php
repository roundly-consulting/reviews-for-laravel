<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Actions;

use Carbon\CarbonImmutable;
use RoundlyConsulting\PackageToolkit\Support\Config;
use RoundlyConsulting\Reviews\Contracts\ReviewModerator;
use RoundlyConsulting\Reviews\DataTransferObjects\ModerationOutcome;
use RoundlyConsulting\Reviews\Enums\ReviewStatus;
use RoundlyConsulting\Reviews\Models\Review;

/**
 * The one moderation pipeline: settles the status of a review whose rating or text was just
 * written — a new review, or an existing one whose edit is being re-moderated — on the unsaved
 * model. The caller saves it and announces the transition.
 *
 * `$approve` short-circuits the moderator (a forced approval or `reviews.auto_approve`).
 * Otherwise the configured {@see ReviewModerator} decides: approve and reject apply as given; an
 * undecided (pending) outcome leaves the review at `$undecided`, or at `reviews.default_status`
 * when that is null.
 *
 * @internal building block of CreateReview / UpdateReview.
 */
final readonly class ModerateReview
{
    public function execute(Review $review, bool $approve = false, ?ReviewStatus $undecided = null): ModerationOutcome
    {
        $outcome = $approve
            ? ModerationOutcome::approve()
            : app(ReviewModerator::class)->moderate($review);

        if ($outcome->decision->isApprove()) {
            $this->settle($review, ReviewStatus::Approved);
        } elseif ($outcome->decision->isReject()) {
            $this->settle($review, ReviewStatus::Rejected);

            if ($outcome->reason !== null) {
                $meta = $review->meta ?? collect();
                $meta->put('rejection_reason', $outcome->reason);
                $review->meta = $meta;
            }
        } else {
            $this->settle($review, $undecided ?? $this->defaultStatus());
        }

        return $outcome;
    }

    /**
     * An approval keeps an existing `approved_at` (an approved review that stays approved has not
     * been approved again) and stamps a fresh one otherwise; every other status clears it.
     */
    private function settle(Review $review, ReviewStatus $status): void
    {
        $review->status = $status;

        if ($status->isApproved()) {
            $review->approved_at ??= CarbonImmutable::now();

            return;
        }

        $review->approved_at = null;
    }

    private function defaultStatus(): ReviewStatus
    {
        return Config::enumOr('reviews.default_status', ReviewStatus::class, ReviewStatus::Pending);
    }
}
