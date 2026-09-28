<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Traits\Macroable;
use RoundlyConsulting\Reviews\Actions\ApproveReview;
use RoundlyConsulting\Reviews\Actions\CreateReview;
use RoundlyConsulting\Reviews\Actions\DeleteReview;
use RoundlyConsulting\Reviews\Actions\MarkReviewVerified;
use RoundlyConsulting\Reviews\Actions\RejectReview;
use RoundlyConsulting\Reviews\Actions\RemoveReviewVote;
use RoundlyConsulting\Reviews\Actions\RespondToReview;
use RoundlyConsulting\Reviews\Actions\UpdateReview;
use RoundlyConsulting\Reviews\Actions\VoteOnReview;
use RoundlyConsulting\Reviews\DataTransferObjects\CreateReviewData;
use RoundlyConsulting\Reviews\DataTransferObjects\UpdateReviewData;
use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Models\ReviewVote;
use RoundlyConsulting\Reviews\Support\ReviewableScope;
use RoundlyConsulting\Reviews\Support\ReviewModel;
use RoundlyConsulting\Reviews\Testing\ReviewsFake;

/**
 * The reviews public API: the root behind the {@see Reviews} facade, and the class to inject
 * when you prefer dependency injection.
 *
 * - `for($reviewable)` scopes authoring (`->by($author)->…->create()`), aggregates and listing
 *   queries to one subject.
 * - `query()` is the swap-aware query over every review (the moderation queue).
 * - Flat verbs (`create`, `approve`, `reject`, `update`, `delete`, `respond`, `vote`,
 *   `removeVote`, `verify`, `unverify`) act on the review they are handed.
 *
 * Every mutation — from the facade, an injected manager, a `for()` builder, a model trait or a
 * `Review` model method — funnels through one of the flat verbs here, each of which resolves one
 * action from the container. A host's container override therefore applies everywhere, and
 * {@see ReviewsFake} sees every call.
 */
class ReviewsManager
{
    use Macroable;

    public function __construct(
        protected readonly Container $container,
    ) {}

    /** Scope authoring, aggregates and listing queries to one reviewable subject. */
    public function for(Model $reviewable): ReviewableScope
    {
        return new ReviewableScope($this, $reviewable);
    }

    /**
     * A query over every review on the configured (`reviews.model`) model — every subject,
     * every status, responses included. Chain the model's scopes for a moderation queue:
     * `Reviews::query()->pending()->latestFirst()->paginate()`.
     *
     * @return Builder<Review>
     */
    public function query(): Builder
    {
        return ReviewModel::query();
    }

    public function create(CreateReviewData $data): Review
    {
        return $this->container->make(CreateReview::class)->execute($data);
    }

    public function approve(Review $review): Review
    {
        return $this->container->make(ApproveReview::class)->execute($review);
    }

    public function reject(Review $review, ?string $reason = null): Review
    {
        return $this->container->make(RejectReview::class)->execute($review, $reason);
    }

    public function update(Review $review, UpdateReviewData $data): Review
    {
        return $this->container->make(UpdateReview::class)->execute($review, $data);
    }

    public function delete(Review $review): void
    {
        $this->container->make(DeleteReview::class)->execute($review);
    }

    public function respond(Review $review, Model $author, string $content, ?string $title = null): Review
    {
        return $this->container->make(RespondToReview::class)->execute($review, $author, $content, $title);
    }

    public function vote(Review $review, Model $voter, bool $helpful = true): ReviewVote
    {
        return $this->container->make(VoteOnReview::class)->execute($review, $voter, $helpful);
    }

    public function removeVote(Review $review, Model $voter): void
    {
        $this->container->make(RemoveReviewVote::class)->execute($review, $voter);
    }

    /** Flag a review as verified (a verified purchase / reviewer). Idempotent. */
    public function verify(Review $review): Review
    {
        return $this->container->make(MarkReviewVerified::class)->execute($review, true);
    }

    /** Clear a review's verified flag. Idempotent. */
    public function unverify(Review $review): Review
    {
        return $this->container->make(MarkReviewVerified::class)->execute($review, false);
    }
}
