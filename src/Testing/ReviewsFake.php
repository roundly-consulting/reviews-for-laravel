<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Testing;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Assert;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Models\ReviewVote;
use RoundlyConsulting\Reviews\Reviews;
use RoundlyConsulting\Reviews\Support\PendingReview;

/**
 * A recording, still-performing variant of {@see Reviews} for host-application
 * tests. Operations run against the database as usual while assertions verify
 * intent, matching Laravel's *::fake() ergonomics.
 *
 * This class lives in src/ so host apps can use it; it depends on PHPUnit's
 * Assert, which is always present in a Laravel app's dev dependencies.
 */
final class ReviewsFake extends Reviews
{
    /** @var list<Review> */
    private array $created = [];

    /** @var list<Review> */
    private array $approved = [];

    /** @var list<Review> */
    private array $rejected = [];

    /** @var list<Review> */
    private array $responded = [];

    /** @var list<Review> */
    private array $voted = [];

    public function for(Model $reviewable): PendingReview
    {
        return (new RecordingPendingReview($this))->for($reviewable);
    }

    public function recordCreated(Review $review): void
    {
        $this->created[] = $review;
    }

    public function approve(Review $review): Review
    {
        $review = parent::approve($review);

        $this->approved[] = $review;

        return $review;
    }

    public function reject(Review $review, ?string $reason = null): Review
    {
        $review = parent::reject($review, $reason);

        $this->rejected[] = $review;

        return $review;
    }

    public function respond(Review $review, Model $author, string $content, ?string $title = null): Review
    {
        $response = parent::respond($review, $author, $content, $title);

        $this->responded[] = $response;

        return $response;
    }

    public function vote(Review $review, Model $voter, bool $helpful = true): ReviewVote
    {
        $vote = parent::vote($review, $voter, $helpful);

        $this->voted[] = $review;

        return $vote;
    }

    public function assertReviewCreated(?callable $callback = null): void
    {
        if ($callback === null) {
            Assert::assertNotEmpty($this->created, 'Expected a review to be created, but none were.');

            return;
        }

        Assert::assertTrue(
            $this->matches($this->created, $callback),
            'Expected a created review matching the callback, but none did.',
        );
    }

    public function assertReviewApproved(?callable $callback = null): void
    {
        if ($callback === null) {
            Assert::assertNotEmpty($this->approved, 'Expected a review to be approved, but none were.');

            return;
        }

        Assert::assertTrue(
            $this->matches($this->approved, $callback),
            'Expected an approved review matching the callback, but none did.',
        );
    }

    public function assertReviewRejected(?callable $callback = null): void
    {
        if ($callback === null) {
            Assert::assertNotEmpty($this->rejected, 'Expected a review to be rejected, but none were.');

            return;
        }

        Assert::assertTrue(
            $this->matches($this->rejected, $callback),
            'Expected a rejected review matching the callback, but none did.',
        );
    }

    public function assertReviewResponded(?callable $callback = null): void
    {
        if ($callback === null) {
            Assert::assertNotEmpty($this->responded, 'Expected a response to be created, but none were.');

            return;
        }

        Assert::assertTrue(
            $this->matches($this->responded, $callback),
            'Expected a response matching the callback, but none did.',
        );
    }

    public function assertReviewVoted(?callable $callback = null): void
    {
        if ($callback === null) {
            Assert::assertNotEmpty($this->voted, 'Expected a review to be voted on, but none were.');

            return;
        }

        Assert::assertTrue(
            $this->matches($this->voted, $callback),
            'Expected a voted review matching the callback, but none did.',
        );
    }

    public function assertNothingReviewed(): void
    {
        Assert::assertEmpty($this->created, 'Expected no reviews to be created.');
    }

    /**
     * @param  list<Review>  $reviews
     */
    private function matches(array $reviews, callable $callback): bool
    {
        foreach ($reviews as $review) {
            if ($callback($review) === true) {
                return true;
            }
        }

        return false;
    }
}
