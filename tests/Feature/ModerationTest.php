<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\Contracts\ReviewModerator;
use RoundlyConsulting\Reviews\DataTransferObjects\ModerationOutcome;
use RoundlyConsulting\Reviews\Enums\ModerationDecision;
use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Moderation\NullModerator;
use RoundlyConsulting\Reviews\Moderation\WordListModerator;
use RoundlyConsulting\Reviews\Tests\Entity;
use RoundlyConsulting\Reviews\Tests\Product;

it('leaves reviews pending under the null moderator', function (): void {
    $review = Reviews::for(Product::query()->create())
        ->by(Entity::query()->create())
        ->content('Lovely')
        ->create();

    expect($review->isPending())->toBeTrue();
});

it('auto-rejects a review containing a banned word', function (): void {
    config()->set('reviews.moderator', WordListModerator::class);
    config()->set('reviews.moderation.banned_words', ['spam', 'scam']);

    $review = Reviews::for(Product::query()->create())
        ->by(Entity::query()->create())
        ->content('This is a scam')
        ->create();

    expect($review->isRejected())->toBeTrue()
        ->and($review->meta?->get('rejection_reason'))->not->toBeNull();
});

it('lets clean reviews through the word-list moderator', function (): void {
    config()->set('reviews.moderator', WordListModerator::class);
    config()->set('reviews.moderation.banned_words', ['spam']);

    $review = Reviews::for(Product::query()->create())
        ->by(Entity::query()->create())
        ->content('Genuinely great product')
        ->create();

    expect($review->isPending())->toBeTrue();
});

it('matches banned words whole-word and case-insensitively', function (): void {
    config()->set('reviews.moderation.banned_words', ['SPAM']);
    $moderator = new WordListModerator;

    $hit = new Review(['content' => 'pure spam here']);
    $miss = new Review(['content' => 'spammy but allowed']);

    expect($moderator->moderate($hit)->decision)->toBe(ModerationDecision::Reject)
        ->and($moderator->moderate($miss)->decision)->toBe(ModerationDecision::Pending);
});

it('stays pending when the banned list is empty or content is blank', function (): void {
    $moderator = new WordListModerator([]);
    expect($moderator->moderate(new Review(['content' => 'anything']))->decision)
        ->toBe(ModerationDecision::Pending);

    $withList = new WordListModerator(['x']);
    expect($withList->moderate(new Review)->decision)->toBe(ModerationDecision::Pending);
});

it('approves through a custom moderator', function (): void {
    app()->bind(ReviewModerator::class, fn (): ReviewModerator => new class implements ReviewModerator
    {
        public function moderate(Review $review): ModerationOutcome
        {
            return ModerationOutcome::approve();
        }
    });

    $review = Reviews::for(Product::query()->create())
        ->by(Entity::query()->create())
        ->content('Fine')
        ->create();

    expect($review->isApproved())->toBeTrue()
        ->and($review->approved_at)->not->toBeNull();
});

it('resolves the configured moderator from the container', function (): void {
    expect(app(ReviewModerator::class))->toBeInstanceOf(NullModerator::class);
});
