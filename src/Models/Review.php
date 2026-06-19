<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use RoundlyConsulting\Reviews\Concerns\HasReviewScopes;
use RoundlyConsulting\Reviews\Database\Factories\ReviewFactory;
use RoundlyConsulting\Reviews\Enums\ReviewStatus;

/**
 * @property int $id
 * @property string|null $reviewable_type
 * @property int|null $reviewable_id
 * @property string|null $author_type
 * @property int|null $author_id
 * @property int|null $rating
 * @property ReviewStatus $status
 * @property string|null $title
 * @property string|null $content
 * @property Collection<string, mixed>|null $meta
 * @property CarbonImmutable|null $approved_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 */
class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    use HasReviewScopes;
    use SoftDeletes;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'status' => ReviewStatus::class,
            'meta' => 'collection',
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
