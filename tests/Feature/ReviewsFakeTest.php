<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\AssertionFailedError;
use RoundlyConsulting\Reviews\Actions\VoteOnReview;
use RoundlyConsulting\Reviews\DataTransferObjects\CreateReviewData;
use RoundlyConsulting\Reviews\DataTransferObjects\UpdateReviewData;
use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\ReviewsManager;
use RoundlyConsulting\Reviews\Testing\ReviewsFake;
use RoundlyConsulting\Reviews\Tests\Entity;
use RoundlyConsulting\Reviews\Tests\Product;
use RoundlyConsulting\Reviews\Tests\Voter;

it('swaps in a recording fake that subtypes the manager', function (): void {
    $fake = Reviews::fake();

    expect($fake)->toBeInstanceOf(ReviewsFake::class)
        ->toBeInstanceOf(ReviewsManager::class)
        ->and(app(ReviewsManager::class))->toBe($fake)
        ->and(Reviews::getFacadeRoot())->toBe($fake);
});

it('still performs every operation', function (): void {
    Reviews::fake();

    $review = Reviews::for(Product::query()->create())->by(Entity::query()->create())->rating(5)->create();

    expect(Review::query()->find($review->getKey()))->not->toBeNull();
});

/**
 * One row per recorded operation: how to perform it (through the facade), the assertion that
 * must pass afterwards, its callback matcher, and the assertNothing* twin.
 *
 * @return array<string, array{Closure(Review): void, string, Closure, string}>
 */
dataset('recorded operations', fn (): array => [
    'create via for()->by()' => [
        fn (Review $review): Review => Reviews::for(Product::query()->create())->by(Entity::query()->create())->rating(4)->create(),
        'assertReviewCreated',
        fn (Review $created): bool => $created->rating === 4,
        'assertNothingReviewed',
    ],
    'create via DTO' => [
        fn (Review $review): Review => Reviews::create(new CreateReviewData(author: Entity::query()->create(), reviewable: Product::query()->create(), rating: 4)),
        'assertReviewCreated',
        fn (Review $created): bool => $created->rating === 4,
        'assertNothingReviewed',
    ],
    'approve' => [
        fn (Review $review): Review => Reviews::approve($review),
        'assertReviewApproved',
        fn (Review $approved): bool => $approved->isApproved(),
        'assertNothingApproved',
    ],
    'reject' => [
        fn (Review $review): Review => Reviews::reject($review, 'spam'),
        'assertReviewRejected',
        fn (Review $rejected): bool => $rejected->meta?->get('rejection_reason') === 'spam',
        'assertNothingRejected',
    ],
    'update' => [
        fn (Review $review): Review => Reviews::update($review, new UpdateReviewData(title: 'Edited')),
        'assertReviewUpdated',
        fn (Review $updated): bool => $updated->title === 'Edited',
        'assertNothingUpdated',
    ],
    'delete' => [
        fn (Review $review) => Reviews::delete($review),
        'assertReviewDeleted',
        fn (Review $deleted): bool => $deleted->trashed(),
        'assertNothingDeleted',
    ],
    'respond' => [
        fn (Review $review): Review => Reviews::respond($review, Entity::query()->create(), 'Thanks'),
        'assertReviewResponded',
        fn (Review $response, Review $parent): bool => $response->parent_id === $parent->getKey(),
        'assertNothingResponded',
    ],
    'vote' => [
        fn (Review $review) => Reviews::vote($review, Voter::query()->create()),
        'assertReviewVoted',
        fn (Review $review, Model $voter): bool => $voter instanceof Voter,
        'assertNothingVoted',
    ],
    'remove a vote' => [
        // Seeded through the action, so the only recorded call is the removal itself.
        fn (Review $review) => Reviews::removeVote($review, tap(Voter::query()->create(), fn (Voter $voter) => app(VoteOnReview::class)->execute($review, $voter))),
        'assertReviewVoteRemoved',
        fn (Review $review, Model $voter): bool => $voter instanceof Voter,
        'assertNoVoteRemoved',
    ],
    'verify' => [
        fn (Review $review): Review => Reviews::verify($review),
        'assertReviewVerified',
        fn (Review $verified): bool => $verified->verified,
        'assertNothingVerified',
    ],
    'unverify' => [
        fn (Review $review): Review => Reviews::unverify($review),
        'assertReviewUnverified',
        fn (Review $unverified): bool => ! $unverified->verified,
        'assertNothingUnverified',
    ],
]);

it('records the operation and passes its assertions', function (Closure $perform, string $assert, Closure $matches, string $assertNothing): void {
    $fake = Reviews::fake();
    $review = Review::factory()->pending()->create(['verified' => $assert === 'assertReviewUnverified']);

    $fake->{$assertNothing}();

    $perform($review);

    $fake->{$assert}();
    $fake->{$assert}($matches);
})->with('recorded operations');

it('fails each assertion when nothing was recorded', function (Closure $perform, string $assert, Closure $matches, string $assertNothing): void {
    $fake = Reviews::fake();

    expect(fn () => $fake->{$assert}())->toThrow(AssertionFailedError::class, 'but none were');
})->with('recorded operations');

it('fails each assertion when no recorded call matches the callback', function (Closure $perform, string $assert, Closure $matches, string $assertNothing): void {
    $fake = Reviews::fake();
    $review = Review::factory()->pending()->create(['verified' => $assert === 'assertReviewUnverified']);

    $perform($review);

    expect(fn () => $fake->{$assert}(fn (): bool => false))->toThrow(AssertionFailedError::class, 'matching the callback');
})->with('recorded operations');

it('fails each assertNothing* once the operation was recorded', function (Closure $perform, string $assert, Closure $matches, string $assertNothing): void {
    $fake = Reviews::fake();
    $review = Review::factory()->pending()->create(['verified' => $assert === 'assertReviewUnverified']);

    $perform($review);

    expect(fn () => $fake->{$assertNothing}())->toThrow(AssertionFailedError::class, 'but 1 were');
})->with('recorded operations');

it('records nothing for a call that changed nothing', function (Closure $noop, string $assertNothing): void {
    // The real actions return early on these — no write, no event — so there is nothing to assert.
    $fake = Reviews::fake();

    $noop();

    $fake->{$assertNothing}();
})->with([
    'approving an approved review' => [fn () => Reviews::approve(Review::factory()->approved()->create()), 'assertNothingApproved'],
    'rejecting a rejected review' => [fn () => Reviews::reject(Review::factory()->rejected()->create(), 'again'), 'assertNothingRejected'],
    'verifying a verified review' => [fn () => Reviews::verify(Review::factory()->create(['verified' => true])), 'assertNothingVerified'],
    'unverifying an unverified review' => [fn () => Reviews::unverify(Review::factory()->create(['verified' => false])), 'assertNothingUnverified'],
    'removing a vote never cast' => [fn () => Reviews::removeVote(Review::factory()->create(), Voter::query()->create()), 'assertNoVoteRemoved'],
    'withdrawing via CanVoteOnReviews with no vote' => [fn () => Voter::query()->create()->removeVoteFrom(Review::factory()->create()), 'assertNoVoteRemoved'],
]);

it('records a real transition after a no-op one', function (): void {
    $fake = Reviews::fake();
    $review = Review::factory()->rejected()->create();

    Reviews::reject($review, 'again');
    $fake->assertNothingRejected();

    Reviews::approve($review);
    $fake->assertReviewApproved(fn (Review $approved): bool => $approved->is($review));
});

it('tells whether a vote was removed', function (): void {
    $review = Review::factory()->create();
    $voter = Voter::query()->create();

    expect(Reviews::removeVote($review, $voter))->toBeFalse()
        ->and($voter->removeVoteFrom($review))->toBeFalse();

    Reviews::vote($review, $voter);

    expect($voter->removeVoteFrom($review))->toBeTrue();
});

it('keeps each operation in its own bucket', function (): void {
    $fake = Reviews::fake();

    Reviews::approve(Review::factory()->pending()->create());

    $fake->assertReviewApproved();
    $fake->assertNothingRejected();
    $fake->assertNothingReviewed();
    $fake->assertNothingUpdated();
});

describe('calls made through models and traits', function (): void {
    it('records a review added through HasReviews::addReview()', function (): void {
        $fake = Reviews::fake();
        $product = Product::query()->create();

        $product->addReview(Entity::query()->create())->rating(5)->create();

        $fake->assertReviewCreated(fn (Review $review): bool => $review->reviewable?->is($product) === true);
    });

    it('records a response made through Review::respond()', function (): void {
        $fake = Reviews::fake();
        $review = Review::factory()->approved()->create();

        $review->respond(Entity::query()->create(), 'Thanks');

        $fake->assertReviewResponded(fn (Review $response, Review $parent): bool => $parent->is($review));
    });

    it('records verification through Review::markVerified() and markUnverified()', function (): void {
        $fake = Reviews::fake();
        $review = Review::factory()->unverified()->create();

        $review->markVerified();
        $fake->assertReviewVerified(fn (Review $verified): bool => $verified->is($review));
        $fake->assertNothingUnverified();

        $review->markUnverified();
        $fake->assertReviewUnverified(fn (Review $unverified): bool => $unverified->is($review));
    });

    it('records votes cast and withdrawn through CanVoteOnReviews', function (): void {
        $fake = Reviews::fake();
        $review = Review::factory()->approved()->create();
        $voter = Voter::query()->create();

        $voter->voteOn($review);
        $fake->assertReviewVoted(fn (Review $voted, Model $voter): bool => $voter->is($voter));
        $fake->assertNoVoteRemoved();

        $voter->removeVoteFrom($review);
        $fake->assertReviewVoteRemoved(fn (Review $voted, Model $voter): bool => $voter->is($voter));
    });

    it('fails when a model call recorded something else', function (): void {
        $fake = Reviews::fake();
        $review = Review::factory()->approved()->create();

        $review->respond(Entity::query()->create(), 'Thanks');

        expect(fn () => $fake->assertReviewResponded(fn (Review $response, Review $parent): bool => false))
            ->toThrow(AssertionFailedError::class)
            ->and(fn () => $fake->assertNothingResponded())->toThrow(AssertionFailedError::class);
    });
});

it('hands a constructor-injected manager the fake', function (): void {
    $fake = Reviews::fake();

    $service = new class(app(ReviewsManager::class))
    {
        public function __construct(public ReviewsManager $reviews) {}
    };

    $service->reviews->approve(Review::factory()->pending()->create());

    expect($service->reviews)->toBe($fake);
    $fake->assertReviewApproved();
});
