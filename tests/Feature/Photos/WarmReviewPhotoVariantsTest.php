<?php

declare(strict_types=1);

use Illuminate\Events\CallQueuedListener;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RoundlyConsulting\MediaLibrary\Jobs\GenerateVariantsJob;
use RoundlyConsulting\Reviews\Events\ReviewApproved;
use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\Listeners\WarmReviewPhotoVariants;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Tests\Entity;

beforeEach(function (): void {
    Storage::fake('public');
});

function pendingReviewWithPhoto(): Review
{
    return Reviews::for(Entity::create())
        ->by(Entity::create())
        ->rating(5)
        ->content('great')
        ->withPhoto(UploadedFile::fake()->image('a.jpg', 800, 600))
        ->create();
}

it('queues one GenerateVariantsJob per photo', function (): void {
    $review = pendingReviewWithPhoto();

    Queue::fake();

    (new WarmReviewPhotoVariants)->handle(new ReviewApproved($review));

    Queue::assertPushed(GenerateVariantsJob::class, 1);
});

it('queues nothing when the review has no photos', function (): void {
    $review = Reviews::for(Entity::create())->by(Entity::create())->rating(5)->content('great')->create();

    Queue::fake();

    (new WarmReviewPhotoVariants)->handle(new ReviewApproved($review));

    Queue::assertNotPushed(GenerateVariantsJob::class);
});

it('queues nothing when warming is disabled', function (): void {
    config()->set('reviews.photos.warm_on_approval', false);
    $review = pendingReviewWithPhoto();

    Queue::fake();

    (new WarmReviewPhotoVariants)->handle(new ReviewApproved($review));

    Queue::assertNotPushed(GenerateVariantsJob::class);
});

it('queues nothing when photos are disabled', function (): void {
    $review = pendingReviewWithPhoto();
    config()->set('reviews.photos.enabled', false);

    Queue::fake();

    (new WarmReviewPhotoVariants)->handle(new ReviewApproved($review));

    Queue::assertNotPushed(GenerateVariantsJob::class);
});

it('registers the warm listener on ReviewApproved', function (): void {
    $review = pendingReviewWithPhoto();

    Queue::fake();

    Reviews::approve($review);

    Queue::assertPushed(
        CallQueuedListener::class,
        fn (CallQueuedListener $job): bool => $job->class === WarmReviewPhotoVariants::class,
    );
});
