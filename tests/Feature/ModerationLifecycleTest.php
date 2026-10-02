<?php

declare(strict_types=1);

use Illuminate\Events\CallQueuedListener;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RoundlyConsulting\Reviews\Contracts\ReviewModerator;
use RoundlyConsulting\Reviews\DataTransferObjects\ModerationOutcome;
use RoundlyConsulting\Reviews\DataTransferObjects\UpdateReviewData;
use RoundlyConsulting\Reviews\Enums\ReviewStatus;
use RoundlyConsulting\Reviews\Events\ReviewApproved;
use RoundlyConsulting\Reviews\Events\ReviewRejected;
use RoundlyConsulting\Reviews\Events\ReviewUpdated;
use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\Listeners\WarmReviewPhotoVariants;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Moderation\WordListModerator;
use RoundlyConsulting\Reviews\Tests\Entity;
use RoundlyConsulting\Reviews\Tests\Product;

/*
 * One moderation pipeline for creates and edits: the moderator sees the whole review (author and
 * subject included), a review that lands approved or rejected announces it however it got there,
 * and an edit to the rating, title or content is moderated again — without ever resurrecting a
 * rejected review or pulling an owner response into the queue.
 */

/**
 * Bind a moderator that answers with `$decide($review)` and records every review it was shown.
 *
 * @param  callable(Review): ModerationOutcome  $decide
 * @return ArrayObject<int, array{author: mixed, reviewable: mixed, exists: bool}>
 */
function recordingModerator(callable $decide): ArrayObject
{
    $seen = new ArrayObject;

    app()->bind(ReviewModerator::class, fn (): ReviewModerator => new readonly class($decide, $seen) implements ReviewModerator
    {
        /** @param ArrayObject<int, array{author: mixed, reviewable: mixed, exists: bool}> $seen */
        public function __construct(private mixed $decide, private ArrayObject $seen) {}

        public function moderate(Review $review): ModerationOutcome
        {
            $this->seen->append([
                'author' => $review->author,
                'reviewable' => $review->reviewable,
                'exists' => $review->exists,
            ]);

            return ($this->decide)($review);
        }
    });

    return $seen;
}

function approvedReview(array $attributes = []): Review
{
    return Review::factory()
        ->approved()
        ->forReviewable(Product::query()->create())
        ->byAuthor(Entity::query()->create())
        ->create($attributes);
}

describe('on create', function (): void {
    it('shows the moderator the author and the subject', function (): void {
        $trusted = Entity::query()->create();
        $product = Product::query()->create();

        $seen = recordingModerator(fn (Review $review): ModerationOutcome => $review->author?->is($trusted) === true
            ? ModerationOutcome::approve()
            : ModerationOutcome::pending());

        $review = Reviews::for($product)->by($trusted)->content('Trusted voice')->create();

        expect($review->isApproved())->toBeTrue()
            ->and($seen)->toHaveCount(1)
            ->and($seen[0]['author']?->is($trusted))->toBeTrue()
            ->and($seen[0]['reviewable']?->is($product))->toBeTrue()
            ->and($seen[0]['exists'])->toBeFalse();
    });

    it('announces an approval however the review got it', function (callable $arrange, bool $forceApproved): void {
        Event::fake([ReviewApproved::class, ReviewRejected::class]);
        $arrange();

        $builder = Reviews::for(Product::query()->create())->by(Entity::query()->create())->content('Fine');
        $review = ($forceApproved ? $builder->approved() : $builder)->create();

        expect($review->isApproved())->toBeTrue();
        Event::assertDispatchedTimes(ReviewApproved::class, 1);
        Event::assertDispatched(ReviewApproved::class, fn (ReviewApproved $event): bool => $event->review->is($review));
        Event::assertNotDispatched(ReviewRejected::class);
    })->with([
        'forced with approved()' => [fn () => null, true],
        'auto_approve' => [fn () => config()->set('reviews.auto_approve', true), false],
        'a moderator approving' => [fn () => recordingModerator(fn (): ModerationOutcome => ModerationOutcome::approve()), false],
        'default_status approved' => [fn () => config()->set('reviews.default_status', 'approved'), false],
    ]);

    it('announces a rejection by the moderator with its reason', function (): void {
        Event::fake([ReviewApproved::class, ReviewRejected::class]);
        config()->set('reviews.moderator', WordListModerator::class);
        config()->set('reviews.moderation.banned_words', ['scam']);

        $review = Reviews::for(Product::query()->create())->by(Entity::query()->create())->content('A scam')->create();

        expect($review->isRejected())->toBeTrue();
        Event::assertDispatched(ReviewRejected::class, fn (ReviewRejected $event): bool => $event->review->is($review)
            && $event->reason === $review->meta?->get('rejection_reason')
            && $event->reason !== null);
        Event::assertNotDispatched(ReviewApproved::class);
    });

    it('announces nothing for a review left pending', function (): void {
        Event::fake([ReviewApproved::class, ReviewRejected::class]);

        Reviews::for(Product::query()->create())->by(Entity::query()->create())->content('Waiting')->create();

        Event::assertNotDispatched(ReviewApproved::class);
        Event::assertNotDispatched(ReviewRejected::class);
    });

    it('warms the photos of a review approved on create', function (): void {
        Storage::fake('public');
        Queue::fake();

        Reviews::for(Product::query()->create())
            ->by(Entity::query()->create())
            ->rating(5)
            ->withPhoto(UploadedFile::fake()->image('a.jpg', 800, 600))
            ->approved()
            ->create();

        Queue::assertPushed(CallQueuedListener::class, fn (CallQueuedListener $job): bool => $job->class === WarmReviewPhotoVariants::class);
    });
});

describe('on edit', function (): void {
    it('runs the moderator again on an edited review', function (): void {
        $review = Review::factory()->pending()->create();
        $seen = recordingModerator(fn (): ModerationOutcome => ModerationOutcome::approve());
        Event::fake([ReviewApproved::class]);

        $updated = Reviews::update($review, new UpdateReviewData(content: 'Edited'));

        expect($updated->status)->toBe(ReviewStatus::Approved)
            ->and($updated->approved_at)->not->toBeNull()
            ->and($seen)->toHaveCount(1)
            ->and($seen[0]['exists'])->toBeTrue();
        Event::assertDispatchedTimes(ReviewApproved::class, 1);
    });

    it('keeps an approved review live under auto_approve', function (): void {
        config()->set('reviews.auto_approve', true);
        $review = approvedReview(['approved_at' => now()->subDay()]);
        $approvedAt = $review->approved_at;
        Event::fake([ReviewApproved::class]);

        $updated = Reviews::update($review, new UpdateReviewData(content: 'Typo fixed'));

        expect($updated->status)->toBe(ReviewStatus::Approved)
            ->and($updated->approved_at?->equalTo($approvedAt))->toBeTrue();
        // Already approved: no transition, so nothing to announce.
        Event::assertNotDispatched(ReviewApproved::class);
    });

    it('rejects an edit that introduces a banned word', function (bool $reset): void {
        config()->set('reviews.reset_status_on_edit', $reset);
        config()->set('reviews.moderator', WordListModerator::class);
        config()->set('reviews.moderation.banned_words', ['scam']);
        $review = approvedReview(['content' => 'Clean words']);
        Event::fake([ReviewRejected::class]);

        $updated = Reviews::update($review, new UpdateReviewData(content: 'Actually a scam'));

        expect($updated->status)->toBe(ReviewStatus::Rejected)
            ->and($updated->approved_at)->toBeNull()
            ->and($updated->meta?->get('rejection_reason'))->not->toBeNull()
            ->and($updated->fresh()?->status)->toBe(ReviewStatus::Rejected);
        Event::assertDispatched(ReviewRejected::class, fn (ReviewRejected $event): bool => $event->review->is($review) && $event->reason !== null);
    })->with(['reset_status_on_edit on' => true, 'reset_status_on_edit off' => false]);

    it('catches a banned word edited into the title', function (): void {
        config()->set('reviews.moderator', WordListModerator::class);
        config()->set('reviews.moderation.banned_words', ['scam']);
        $review = approvedReview();

        $updated = Reviews::update($review, new UpdateReviewData(title: 'Total scam'));

        expect($updated->status)->toBe(ReviewStatus::Rejected);
    });

    it('never resurrects a rejected review', function (): void {
        $review = Reviews::reject(approvedReview(), 'Spam');
        $seen = recordingModerator(fn (): ModerationOutcome => ModerationOutcome::approve());

        $updated = Reviews::update($review, new UpdateReviewData(content: 'Please let me back in', rating: 5));

        expect($updated->status)->toBe(ReviewStatus::Rejected)
            ->and($updated->meta?->get('rejection_reason'))->toBe('Spam')
            ->and($updated->content)->toBe('Please let me back in')
            ->and($seen)->toHaveCount(0)
            ->and(Reviews::query()->pending()->count())->toBe(0);
    });

    it('never pulls an owner response into the moderation queue', function (): void {
        $response = Reviews::respond(approvedReview(), Entity::query()->create(), 'Thanks!');
        $seen = recordingModerator(fn (): ModerationOutcome => ModerationOutcome::pending());

        $updated = Reviews::update($response, new UpdateReviewData(content: 'Thanks a lot!'));

        expect($updated->status)->toBe(ReviewStatus::Approved)
            ->and($updated->approved_at)->not->toBeNull()
            ->and($seen)->toHaveCount(0)
            ->and(Reviews::query()->pending()->count())->toBe(0);
    });

    it('leaves the status alone when nothing moderated changed', function (): void {
        $review = approvedReview(['content' => 'Same words', 'rating' => 4]);
        $seen = recordingModerator(fn (): ModerationOutcome => ModerationOutcome::reject('should not run'));
        Event::fake([ReviewUpdated::class]);

        $updated = Reviews::update($review, new UpdateReviewData(content: 'Same words', rating: 4, verified: true));

        expect($updated->status)->toBe(ReviewStatus::Approved)
            ->and($updated->verified)->toBeTrue()
            ->and($seen)->toHaveCount(0);
        Event::assertDispatched(ReviewUpdated::class);
    });
});
