<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Reviews\DataTransferObjects\CreateReviewData;
use RoundlyConsulting\Reviews\DataTransferObjects\RatingSummary;
use RoundlyConsulting\Reviews\DataTransferObjects\UpdateReviewData;
use RoundlyConsulting\Reviews\Enums\ReviewStatus;
use RoundlyConsulting\Reviews\Events\ReviewVerified;
use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Models\ReviewVote;
use RoundlyConsulting\Reviews\ReviewsManager;
use RoundlyConsulting\Reviews\Support\ReviewableScope;
use RoundlyConsulting\Reviews\Tests\Entity;
use RoundlyConsulting\Reviews\Tests\Product;

it('approves a review through the facade', function (): void {
    $review = Review::factory()->pending()->create();

    $approved = Reviews::approve($review);

    expect($approved->status)->toBe(ReviewStatus::Approved);
});

it('rejects a review through the facade', function (): void {
    $review = Review::factory()->approved()->create();

    $rejected = Reviews::reject($review, 'Spam');

    expect($rejected->status)->toBe(ReviewStatus::Rejected)
        ->and($rejected->meta?->get('rejection_reason'))->toBe('Spam');
});

it('updates a review through the facade', function (): void {
    $review = Review::factory()->approved()->create(['title' => 'Old']);

    $updated = Reviews::update($review, new UpdateReviewData(title: 'New'));

    expect($updated->title)->toBe('New');
});

it('deletes a review through the facade', function (): void {
    $review = Review::factory()->approved()->create();

    Reviews::delete($review);

    expect(Review::query()->find($review->getKey()))->toBeNull();
});

it('creates a review from a DTO through the facade', function (): void {
    $product = Product::query()->create();
    $author = Entity::query()->create();

    $review = Reviews::create(new CreateReviewData(author: $author, reviewable: $product, rating: 4));

    expect($review->exists)->toBeTrue()
        ->and($review->rating)->toBe(4)
        ->and($review->reviewable->is($product))->toBeTrue();
});

it('responds to a review through the facade', function (): void {
    $review = Review::factory()->approved()->create();

    $response = Reviews::respond($review, Entity::query()->create(), 'Thanks', 'Owner');

    expect($response->parent_id)->toBe($review->getKey())
        ->and($response->title)->toBe('Owner');
});

it('votes and removes a vote through the facade', function (): void {
    $review = Review::factory()->approved()->create();
    $voter = Entity::query()->create();

    expect(Reviews::vote($review, $voter, false))->toBeInstanceOf(ReviewVote::class)
        ->and($review->fresh()?->unhelpful_count)->toBe(1);

    Reviews::removeVote($review, $voter);

    expect($review->fresh()?->unhelpful_count)->toBe(0);
});

it('verifies and unverifies a review through the facade', function (): void {
    Event::fake([ReviewVerified::class]);
    $review = Review::factory()->unverified()->create();

    expect(Reviews::verify($review)->verified)->toBeTrue()
        ->and($review->fresh()?->verified)->toBeTrue();

    expect(Reviews::unverify($review)->verified)->toBeFalse()
        ->and($review->fresh()?->verified)->toBeFalse();

    Event::assertDispatchedTimes(ReviewVerified::class, 2);
});

it('lists every review through the swap-aware query for a moderation queue', function (): void {
    $older = Review::factory()->pending()->create(['created_at' => now()->subDay()]);
    $newer = Review::factory()->pending()->create();
    Review::factory()->approved()->create();

    $queue = Reviews::query()->pending()->latestFirst()->get();

    expect(Reviews::query())->toBeInstanceOf(Builder::class)
        ->and($queue->modelKeys())->toBe([$newer->getKey(), $older->getKey()]);
});

describe('for($reviewable)', function (): void {
    beforeEach(function (): void {
        $this->product = Product::query()->create();
        Review::factory()->for($this->product, 'reviewable')->approved()->rating(5)->create();
        Review::factory()->for($this->product, 'reviewable')->approved()->rating(3)->create();
        Review::factory()->for($this->product, 'reviewable')->approved()->rating(5)->create();
        Review::factory()->for($this->product, 'reviewable')->pending()->rating(1)->create();
    });

    it('returns a scope for the subject', function (): void {
        expect(Reviews::for($this->product))->toBeInstanceOf(ReviewableScope::class);
    });

    it('aggregates approved, top-level reviews only', function (): void {
        Reviews::respond(Review::query()->approved()->firstOrFail(), Entity::query()->create(), 'Thanks');

        $reviews = Reviews::for($this->product);

        expect($reviews->average())->toEqualWithDelta(13 / 3, 0.0001)
            ->and($reviews->count())->toBe(3)
            ->and($reviews->distribution())->toBe([5 => 2, 3 => 1])
            ->and($reviews->photoCount())->toBe(0)
            ->and($reviews->reviewsWithPhotos())->toBe(0);
    });

    it('summarises the subject', function (): void {
        $summary = Reviews::for($this->product)->summary();

        expect($summary)->toBeInstanceOf(RatingSummary::class)
            ->and($summary->count)->toBe(3)
            ->and($summary->distribution)->toBe([5 => 2, 3 => 1]);
    });

    it('queries the subject top-level reviews, every status', function (): void {
        Reviews::respond(Review::query()->approved()->firstOrFail(), Entity::query()->create(), 'Thanks');

        expect(Reviews::for($this->product)->query()->count())->toBe(4)
            ->and(Reviews::for($this->product)->query()->approved()->count())->toBe(3)
            ->and(Reviews::for($this->product)->query()->approved()->mostHelpful()->paginate()->total())->toBe(3);
    });

    it('never reaches another subject', function (): void {
        $other = Product::query()->create();
        Review::factory()->for($other, 'reviewable')->approved()->rating(1)->create();

        $reviews = Reviews::for($other);

        expect($reviews->count())->toBe(1)
            ->and($reviews->average())->toBe(1.0)
            ->and($reviews->distribution())->toBe([1 => 1])
            ->and($reviews->query()->count())->toBe(1)
            ->and(Reviews::for($this->product)->query()->pluck('id')->intersect($reviews->query()->pluck('id')))->toBeEmpty();
    });

    it('reports an empty subject', function (): void {
        $reviews = Reviews::for(Product::query()->create());

        expect($reviews->average())->toBeNull()
            ->and($reviews->count())->toBe(0)
            ->and($reviews->distribution())->toBe([])
            ->and($reviews->summary()->toArray())->toBe([
                'average' => null,
                'count' => 0,
                'distribution' => [],
                'photo_count' => 0,
                'reviews_with_photos' => 0,
            ]);
    });
});

it('serves the same API through an injected manager', function (): void {
    $manager = app(ReviewsManager::class);
    $product = Product::query()->create();

    $review = $manager->for($product)->by(Entity::query()->create())->rating(4)->approved()->create();

    expect($manager)->toBe(app(ReviewsManager::class))
        ->and($manager)->toBe(Reviews::getFacadeRoot())
        ->and($manager->verify($review)->verified)->toBeTrue()
        ->and($manager->for($product)->average())->toBe(4.0);
});
