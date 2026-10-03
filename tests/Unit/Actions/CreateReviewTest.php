<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Reviews\Actions\CreateReview;
use RoundlyConsulting\Reviews\DataTransferObjects\CreateReviewData;
use RoundlyConsulting\Reviews\Enums\ReviewStatus;
use RoundlyConsulting\Reviews\Events\ReviewCreated;
use RoundlyConsulting\Reviews\Exceptions\InvalidRatingException;
use RoundlyConsulting\Reviews\Exceptions\InvalidReviewException;
use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\Tests\Entity;

beforeEach(function (): void {
    $this->action = app(CreateReview::class);
});

it('creates a pending review with a rating by default', function (): void {
    Event::fake(ReviewCreated::class);

    $review = $this->action->execute(new CreateReviewData(
        author: Entity::create(),
        reviewable: Entity::create(),
        content: 'Solid',
        rating: 4,
    ));

    expect($review->rating)->toBe(4)
        ->and($review->status)->toBe(ReviewStatus::Pending)
        ->and($review->approved_at)->toBeNull();

    Event::assertDispatched(ReviewCreated::class);
});

it('allows a rating-only review without content', function (): void {
    $review = $this->action->execute(new CreateReviewData(
        author: Entity::create(),
        reviewable: Entity::create(),
        rating: 5,
    ));

    expect($review->rating)->toBe(5)
        ->and($review->content)->toBeNull();
});

it('allows a content-only review without a rating', function (): void {
    $review = $this->action->execute(new CreateReviewData(
        author: Entity::create(),
        reviewable: Entity::create(),
        content: 'No score, just words',
    ));

    expect($review->rating)->toBeNull()
        ->and($review->content)->toBe('No score, just words');
});

it('rejects an empty review with neither rating nor content', function (): void {
    $this->action->execute(new CreateReviewData(
        author: Entity::create(),
        reviewable: Entity::create(),
    ));
})->throws(InvalidReviewException::class, 'A review must have either a rating or content.');

it('rejects an out-of-range rating', function (): void {
    $this->action->execute(new CreateReviewData(
        author: Entity::create(),
        reviewable: Entity::create(),
        rating: 9,
    ));
})->throws(InvalidRatingException::class);

it('auto-approves when the config flag is set', function (): void {
    config()->set('reviews.auto_approve', true);

    $review = $this->action->execute(new CreateReviewData(
        author: Entity::create(),
        reviewable: Entity::create(),
        content: 'Trusted',
    ));

    expect($review->status)->toBe(ReviewStatus::Approved)
        ->and($review->approved_at)->not->toBeNull();
});

it('auto-approves when the dto requests it', function (): void {
    $review = $this->action->execute(new CreateReviewData(
        author: Entity::create(),
        reviewable: Entity::create(),
        content: 'Force approved',
        approved: true,
    ));

    expect($review->status)->toBe(ReviewStatus::Approved);
});

it('honours a custom default status', function (): void {
    config()->set('reviews.default_status', ReviewStatus::Approved->value);

    $review = $this->action->execute(new CreateReviewData(
        author: Entity::create(),
        reviewable: Entity::create(),
        content: 'Defaulted',
    ));

    expect($review->status)->toBe(ReviewStatus::Approved);
});

it('refuses an unknown default status instead of falling back to pending', function (): void {
    config()->set('reviews.default_status', 'bogus');

    expect(fn (): mixed => $this->action->execute(new CreateReviewData(
        author: Entity::create(),
        reviewable: Entity::create(),
        content: 'Defaulted',
    )))->toThrow(
        InvalidConfigurationException::class,
        'Configuration value [reviews.default_status] must be one of [pending, approved, rejected], [bogus] given.',
    );
});

it('blocks a duplicate when one_per_author is enabled', function (): void {
    config()->set('reviews.one_per_author', true);

    $author = Entity::create();
    $reviewable = Entity::create();

    $this->action->execute(new CreateReviewData(
        author: $author,
        reviewable: $reviewable,
        content: 'First',
    ));

    $this->action->execute(new CreateReviewData(
        author: $author,
        reviewable: $reviewable,
        content: 'Second',
    ));
})->throws(InvalidReviewException::class, 'This author has already reviewed this subject.');

it('lets an author who only responded on a subject review it under one_per_author', function (): void {
    config()->set('reviews.one_per_author', true);

    $seller = Entity::create();
    $product = Entity::create();
    $customerReview = $this->action->execute(new CreateReviewData(author: Entity::create(), reviewable: $product, content: 'Great'));

    Reviews::respond($customerReview, $seller, 'Thanks!');

    $own = $this->action->execute(new CreateReviewData(author: $seller, reviewable: $product, content: 'My own take'));

    expect($own->exists)->toBeTrue()
        ->and($own->isResponse())->toBeFalse();
});

it('allows multiple reviews when one_per_author is disabled', function (): void {
    $author = Entity::create();
    $reviewable = Entity::create();

    $this->action->execute(new CreateReviewData(author: $author, reviewable: $reviewable, content: 'First'));
    $second = $this->action->execute(new CreateReviewData(author: $author, reviewable: $reviewable, content: 'Second'));

    expect($second->exists)->toBeTrue();
});
