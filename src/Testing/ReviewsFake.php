<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Testing;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Assert;
use RoundlyConsulting\Reviews\DataTransferObjects\CreateReviewData;
use RoundlyConsulting\Reviews\DataTransferObjects\UpdateReviewData;
use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Models\ReviewVote;
use RoundlyConsulting\Reviews\ReviewsManager;

/**
 * The recording, still-performing stand-in {@see Reviews::fake()} swaps in.
 *
 * Every operation runs against the database as usual (so reads, aggregates and events behave
 * normally) while each mutation is recorded, from wherever it came: the facade, an injected
 * {@see ReviewsManager}, a `for()->by()->create()` builder, the `HasReviews` /
 * `CanVoteOnReviews` traits, or a `Review` model method (`respond()`, `markVerified()`,
 * `markUnverified()`).
 *
 * Each `assert*()` takes an optional callback, called with the recorded review and — where the
 * operation has one — the other party: the voter for votes, the parent review for responses.
 *
 * This class lives in src/ so host apps can use it; it depends on PHPUnit's Assert, which is
 * always present in a Laravel app's dev dependencies.
 *
 * @phpstan-type Recorded array{review: Review, party: Model|null}
 */
final class ReviewsFake extends ReviewsManager
{
    /** @var array<string, list<Recorded>> */
    private array $recorded = [];

    public function create(CreateReviewData $data): Review
    {
        return $this->record('created', parent::create($data));
    }

    public function approve(Review $review): Review
    {
        $changes = ! $review->isApproved();

        return $this->recordIf($changes, 'approved', parent::approve($review));
    }

    public function reject(Review $review, ?string $reason = null): Review
    {
        $changes = ! $review->isRejected();

        return $this->recordIf($changes, 'rejected', parent::reject($review, $reason));
    }

    public function update(Review $review, UpdateReviewData $data): Review
    {
        return $this->record('updated', parent::update($review, $data));
    }

    public function delete(Review $review): void
    {
        parent::delete($review);

        $this->record('deleted', $review);
    }

    public function respond(Review $review, Model $author, string $content, ?string $title = null): Review
    {
        return $this->record('responded', parent::respond($review, $author, $content, $title), $review);
    }

    public function vote(Review $review, Model $voter, bool $helpful = true): ReviewVote
    {
        $vote = parent::vote($review, $voter, $helpful);

        $this->record('voted', $review, $voter);

        return $vote;
    }

    public function removeVote(Review $review, Model $voter): bool
    {
        $removed = parent::removeVote($review, $voter);

        $this->recordIf($removed, 'voteRemoved', $review, $voter);

        return $removed;
    }

    public function verify(Review $review): Review
    {
        $changes = ! $review->verified;

        return $this->recordIf($changes, 'verified', parent::verify($review));
    }

    public function unverify(Review $review): Review
    {
        $changes = $review->verified;

        return $this->recordIf($changes, 'unverified', parent::unverify($review));
    }

    public function assertReviewCreated(?callable $callback = null): void
    {
        $this->assertRecorded('created', 'a review to be created', $callback);
    }

    public function assertNothingReviewed(): void
    {
        $this->assertNothingRecorded('created', 'no review to be created');
    }

    public function assertReviewApproved(?callable $callback = null): void
    {
        $this->assertRecorded('approved', 'a review to be approved', $callback);
    }

    public function assertNothingApproved(): void
    {
        $this->assertNothingRecorded('approved', 'no review to be approved');
    }

    public function assertReviewRejected(?callable $callback = null): void
    {
        $this->assertRecorded('rejected', 'a review to be rejected', $callback);
    }

    public function assertNothingRejected(): void
    {
        $this->assertNothingRecorded('rejected', 'no review to be rejected');
    }

    public function assertReviewUpdated(?callable $callback = null): void
    {
        $this->assertRecorded('updated', 'a review to be updated', $callback);
    }

    public function assertNothingUpdated(): void
    {
        $this->assertNothingRecorded('updated', 'no review to be updated');
    }

    public function assertReviewDeleted(?callable $callback = null): void
    {
        $this->assertRecorded('deleted', 'a review to be deleted', $callback);
    }

    public function assertNothingDeleted(): void
    {
        $this->assertNothingRecorded('deleted', 'no review to be deleted');
    }

    /** The callback receives the response, then the parent review. */
    public function assertReviewResponded(?callable $callback = null): void
    {
        $this->assertRecorded('responded', 'a response to be created', $callback);
    }

    public function assertNothingResponded(): void
    {
        $this->assertNothingRecorded('responded', 'no response to be created');
    }

    /** The callback receives the review, then the voter. */
    public function assertReviewVoted(?callable $callback = null): void
    {
        $this->assertRecorded('voted', 'a review to be voted on', $callback);
    }

    public function assertNothingVoted(): void
    {
        $this->assertNothingRecorded('voted', 'no review to be voted on');
    }

    /** The callback receives the review, then the voter. */
    public function assertReviewVoteRemoved(?callable $callback = null): void
    {
        $this->assertRecorded('voteRemoved', 'a vote to be removed', $callback);
    }

    public function assertNoVoteRemoved(): void
    {
        $this->assertNothingRecorded('voteRemoved', 'no vote to be removed');
    }

    public function assertReviewVerified(?callable $callback = null): void
    {
        $this->assertRecorded('verified', 'a review to be verified', $callback);
    }

    public function assertNothingVerified(): void
    {
        $this->assertNothingRecorded('verified', 'no review to be verified');
    }

    public function assertReviewUnverified(?callable $callback = null): void
    {
        $this->assertRecorded('unverified', 'a review to be unverified', $callback);
    }

    public function assertNothingUnverified(): void
    {
        $this->assertNothingRecorded('unverified', 'no review to be unverified');
    }

    /**
     * Record only a call that really changed something. Approve, reject, verify, unverify and
     * removeVote are idempotent: on a review already in the target state (or with no vote to
     * remove) the real action returns early — no write, no event — so the fake records nothing
     * and `assert*()` cannot pass over a call that did nothing.
     */
    private function recordIf(bool $changed, string $kind, Review $review, ?Model $party = null): Review
    {
        return $changed ? $this->record($kind, $review, $party) : $review;
    }

    private function record(string $kind, Review $review, ?Model $party = null): Review
    {
        $this->recorded[$kind][] = ['review' => $review, 'party' => $party];

        return $review;
    }

    private function assertRecorded(string $kind, string $expectation, ?callable $callback): void
    {
        $calls = $this->recorded[$kind] ?? [];

        if ($callback === null) {
            Assert::assertNotEmpty($calls, "Expected {$expectation}, but none were.");

            return;
        }

        $matched = false;

        foreach ($calls as $call) {
            if ($callback($call['review'], $call['party']) === true) {
                $matched = true;

                break;
            }
        }

        Assert::assertTrue($matched, "Expected {$expectation} matching the callback, but none did.");
    }

    private function assertNothingRecorded(string $kind, string $expectation): void
    {
        $count = count($this->recorded[$kind] ?? []);

        Assert::assertSame(0, $count, "Expected {$expectation}, but {$count} were.");
    }
}
