<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\MediaLibrary\Support\MediaModel;
use RoundlyConsulting\PackageToolkit\Support\Config;
use RoundlyConsulting\Reviews\DataTransferObjects\CreateReviewData;
use RoundlyConsulting\Reviews\Events\ReviewApproved;
use RoundlyConsulting\Reviews\Events\ReviewCreated;
use RoundlyConsulting\Reviews\Events\ReviewRejected;
use RoundlyConsulting\Reviews\Exceptions\InvalidReviewException;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Support\PendingPhoto;
use RoundlyConsulting\Reviews\Support\ReviewModel;
use RoundlyConsulting\Reviews\Support\ReviewsConfig;

final readonly class CreateReview
{
    public function __construct(
        private ValidatesRating $validateRating,
        private ValidatesReviewContent $validateContent,
        private ModerateReview $moderate,
    ) {}

    public function execute(CreateReviewData $data): Review
    {
        $this->validateContent->execute($data->rating, $data->content);

        $this->validateRating->execute($data->rating);

        // Fast path: refuse an obvious duplicate before the moderator spends anything on it. The
        // authoritative check runs again under the author's row lock in persist().
        $this->guardAgainstDuplicate($data);

        $review = $this->newReview();

        $review->title = $data->title;
        $review->content = $data->content;
        $review->rating = $data->rating;
        $review->meta = $data->meta;
        $review->verified = $data->verified;

        // Associated before moderation, so a moderator can judge the author and the subject.
        $review->author()->associate($data->author);
        $review->reviewable()->associate($data->reviewable);

        $outcome = $this->moderate->execute(
            $review,
            approve: $data->approved || Config::boolean('reviews.auto_approve'),
        );

        $this->persist($review, $data);

        // Dispatch after commit so listeners and broadcasts see the persisted photos. A review
        // that lands approved or rejected announces it like a later approve()/reject() would, so
        // its photos are warmed and "review went live" listeners run however it got there.
        ReviewCreated::dispatch($review);

        if ($review->isApproved()) {
            ReviewApproved::dispatch($review);
        } elseif ($review->isRejected()) {
            ReviewRejected::dispatch($review, $outcome->reason);
        }

        return $review;
    }

    /**
     * Persist the review and bind its photos atomically: an over-limit request or any failed
     * upload rolls the whole create back, so no review is left without its expected gallery.
     *
     * With `one_per_author` on, the same transaction first takes the author's row lock and
     * re-checks for a duplicate, so one author's concurrent submissions (a double-click, a retry)
     * serialize and the second one's lookup sees the first one's committed review. (SQLite has
     * no row locks; it serializes writers instead.)
     *
     * The transaction runs on the review model's own connection. media-library's connection (when
     * photos are attached) and the author's (when it is locked) get a transaction of their own
     * around it when they differ — still one commit per connection, not a distributed one.
     */
    private function persist(Review $review, CreateReviewData $data): void
    {
        $onePerAuthor = $this->onePerAuthor();

        $write = function () use ($review, $data, $onePerAuthor): void {
            if ($onePerAuthor) {
                $this->lockAuthor($data->author);
                $this->guardAgainstDuplicate($data);
            }

            $review->save();

            $this->attachPhotos($review, $data->photos);
        };

        $connections = [$review->getConnection()];

        if ($data->photos !== []) {
            $connections[] = MediaModel::new()->getConnection();
        }

        if ($onePerAuthor) {
            $connections[] = $data->author->getConnection();
        }

        $run = $write;
        $wrapped = [];

        foreach ($connections as $connection) {
            if (in_array($connection, $wrapped, true)) {
                continue;
            }

            $wrapped[] = $connection;
            $run = static fn () => $connection->transaction($run);
        }

        $run();
    }

    /** Take the author's row lock for the rest of the enclosing transaction. */
    private function lockAuthor(Model $author): void
    {
        if (! $author->exists) {
            return;
        }

        $author->newQueryWithoutScopes()
            ->whereKey($author->getKey())
            ->lockForUpdate()
            ->value($author->getKeyName());
    }

    /**
     * @param  list<PendingPhoto>  $photos
     */
    private function attachPhotos(Review $review, array $photos): void
    {
        if ($photos === []) {
            return;
        }

        if (! Config::boolean('reviews.photos.enabled', true)) {
            throw InvalidReviewException::photosDisabled();
        }

        $this->guardPhotoLimit($review, count($photos));

        $bucket = $review->photosBucket();

        foreach ($photos as $photo) {
            match ($photo->type) {
                PendingPhoto::TYPE_FILE => $review->addMedia($photo->value)->toMediaBucket($bucket),
                PendingPhoto::TYPE_URL => $review->addMediaFromUrl((string) $photo->value)->toMediaBucket($bucket),
                PendingPhoto::TYPE_DISK => $review->addMediaFromDisk((string) $photo->value, $photo->disk)->toMediaBucket($bucket),
                PendingPhoto::TYPE_DRAFT => $review->attachDraftMedia((string) $photo->value, $bucket),
                default => throw InvalidReviewException::photosDisabled(),
            };
        }
    }

    private function guardPhotoLimit(Review $review, int $incoming): void
    {
        $max = ReviewsConfig::photoLimit();

        if ($max <= 0) {
            return;
        }

        $existing = $review->photos()->count();

        if ($existing + $incoming > $max) {
            throw InvalidReviewException::tooManyPhotos($max);
        }
    }

    private function onePerAuthor(): bool
    {
        return Config::boolean('reviews.one_per_author');
    }

    private function guardAgainstDuplicate(CreateReviewData $data): void
    {
        if (! $this->onePerAuthor()) {
            return;
        }

        // Reviews only: an owner response the author wrote on this subject is not a review of it.
        $exists = $this->newReview()->newQuery()
            ->topLevel()
            ->authoredBy($data->author)
            ->for($data->reviewable)
            ->exists();

        if ($exists) {
            throw InvalidReviewException::duplicate();
        }
    }

    private function newReview(): Review
    {
        return ReviewModel::new();
    }
}
