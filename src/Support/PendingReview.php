<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use RoundlyConsulting\PackageToolkit\Support\Config;
use RoundlyConsulting\Reviews\DataTransferObjects\CreateReviewData;
use RoundlyConsulting\Reviews\Exceptions\InvalidReviewException;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\ReviewsManager;

/**
 * Fluent builder for one review — `Reviews::for($subject)->by($author)`. The subject and author
 * are fixed when the builder is made; chain the optional setters, then `create()`, which hands a
 * {@see CreateReviewData} to {@see ReviewsManager::create()} (so the fake records it).
 */
final class PendingReview
{
    private ?int $rating = null;

    private ?string $title = null;

    private ?string $content = null;

    /** @var Collection<string, mixed>|null */
    private ?Collection $meta = null;

    private bool $approved = false;

    private bool $verified = false;

    /** @var list<PendingPhoto> */
    private array $photos = [];

    public function __construct(
        private readonly ReviewsManager $manager,
        private readonly Model $reviewable,
        private readonly Model $author,
    ) {}

    public function rating(?int $rating): self
    {
        $this->rating = $rating;

        return $this;
    }

    public function title(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function content(?string $content): self
    {
        $this->content = $content;

        return $this;
    }

    /**
     * @param  array<string, mixed>|Collection<string, mixed>|null  $meta
     */
    public function meta(array|Collection|null $meta): self
    {
        $this->meta = $meta === null ? null : collect($meta);

        return $this;
    }

    public function approved(): self
    {
        $this->approved = true;

        return $this;
    }

    public function verified(bool $verified = true): self
    {
        $this->verified = $verified;

        return $this;
    }

    /**
     * Queue a photo (an uploaded file or a disk path/URL string) onto the review. Photos are
     * attached to the review's photos bucket inside the create transaction, before ReviewCreated
     * dispatches, so listeners and broadcasts see them.
     */
    public function withPhoto(UploadedFile|string $file): self
    {
        $this->guardPhotosEnabled();

        $this->photos[] = PendingPhoto::file($file);

        return $this;
    }

    /**
     * @param  iterable<int, UploadedFile|string>  $files
     */
    public function withPhotos(iterable $files): self
    {
        foreach ($files as $file) {
            $this->withPhoto($file);
        }

        return $this;
    }

    public function withPhotoFromUrl(string $url): self
    {
        $this->guardPhotosEnabled();

        $this->photos[] = PendingPhoto::url($url);

        return $this;
    }

    public function withPhotoFromDisk(string $path, ?string $disk = null): self
    {
        $this->guardPhotosEnabled();

        $this->photos[] = PendingPhoto::disk($path, $disk);

        return $this;
    }

    /**
     * Queue a previously-uploaded draft media (by its token) onto the review.
     */
    public function withDraftPhoto(string $token): self
    {
        $this->guardPhotosEnabled();

        $this->photos[] = PendingPhoto::draft($token);

        return $this;
    }

    public function create(): Review
    {
        return $this->manager->create(new CreateReviewData(
            author: $this->author,
            reviewable: $this->reviewable,
            content: $this->content,
            title: $this->title,
            rating: $this->rating,
            meta: $this->meta,
            approved: $this->approved,
            verified: $this->verified,
            photos: $this->photos,
        ));
    }

    private function guardPhotosEnabled(): void
    {
        if (! Config::boolean('reviews.photos.enabled', true)) {
            throw InvalidReviewException::photosDisabled();
        }
    }
}
