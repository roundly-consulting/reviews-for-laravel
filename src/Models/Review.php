<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use RoundlyConsulting\MediaLibrary\Contracts\HasMedia;
use RoundlyConsulting\Reviews\Concerns\HasReviewPhotos;
use RoundlyConsulting\Reviews\Concerns\HasReviewScopes;
use RoundlyConsulting\Reviews\Database\Factories\ReviewFactory;
use RoundlyConsulting\Reviews\Enums\ReviewStatus;
use RoundlyConsulting\Reviews\ReviewsManager;
use RoundlyConsulting\Reviews\Support\ReviewVoteModel;

/**
 * @property int $id
 * @property int|null $parent_id
 * @property string|null $reviewable_type
 * @property int|null $reviewable_id
 * @property string|null $author_type
 * @property int|null $author_id
 * @property int|null $rating
 * @property ReviewStatus $status
 * @property bool $verified
 * @property string|null $title
 * @property string|null $content
 * @property Collection<string, mixed>|null $meta
 * @property int $helpful_count
 * @property int $unhelpful_count
 * @property CarbonImmutable|null $approved_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 */
class Review extends Model implements HasMedia
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    use HasReviewPhotos;
    use HasReviewScopes;
    use SoftDeletes;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'status' => ReviewStatus::class,
            'verified' => 'boolean',
            'meta' => 'collection',
            'helpful_count' => 'integer',
            'unhelpful_count' => 'integer',
            'approved_at' => 'immutable_datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphTo<Model, $this> */
    public function author(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Review, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Review, $this> */
    public function responses(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<ReviewVote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(ReviewVoteModel::class(), 'review_id');
    }

    public function helpfulScore(): int
    {
        return $this->helpful_count - $this->unhelpful_count;
    }

    public function isResponse(): bool
    {
        return $this->parent_id !== null;
    }

    /** Respond to this review — {@see ReviewsManager::respond()}. */
    public function respond(Model $author, string $content, ?string $title = null): Review
    {
        return app(ReviewsManager::class)->respond($this, $author, $content, $title);
    }

    /** Flag this review as verified — {@see ReviewsManager::verify()}. */
    public function markVerified(): Review
    {
        return app(ReviewsManager::class)->verify($this);
    }

    /** Clear this review's verified flag — {@see ReviewsManager::unverify()}. */
    public function markUnverified(): Review
    {
        return app(ReviewsManager::class)->unverify($this);
    }

    public function isPending(): bool
    {
        return $this->status->isPending();
    }

    public function isApproved(): bool
    {
        return $this->status->isApproved();
    }

    public function isRejected(): bool
    {
        return $this->status->isRejected();
    }

    protected static function newFactory(): ReviewFactory
    {
        return ReviewFactory::new();
    }
}
