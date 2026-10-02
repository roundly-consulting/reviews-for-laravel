<?php

declare(strict_types=1);

use Illuminate\Events\CallQueuedListener;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RoundlyConsulting\Reviews\Contracts\ReviewModerator;
use RoundlyConsulting\Reviews\DataTransferObjects\ModerationOutcome;
use RoundlyConsulting\Reviews\Events\ReviewApproved;
use RoundlyConsulting\Reviews\Events\ReviewRejected;
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
