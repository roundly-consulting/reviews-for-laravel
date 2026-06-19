<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RoundlyConsulting\Reviews\Database\Factories\ReviewVoteFactory;

/**
 * @property int $id
 * @property int $review_id
 * @property string|null $voter_type
 * @property int|null $voter_id
 * @property bool $helpful
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class ReviewVote extends Model
{
    /** @use HasFactory<ReviewVoteFactory> */
    use HasFactory;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'helpful' => 'boolean',
        ];
    }

    /** @return BelongsTo<Review, $this> */
    public function review(): BelongsTo
    {
        /** @var class-string<Review> $model */
        $model = config('reviews.model', Review::class);

        return $this->belongsTo($model, 'review_id');
    }

    /** @return MorphTo<Model, $this> */
    public function voter(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function newFactory(): ReviewVoteFactory
    {
        return ReviewVoteFactory::new();
    }
}
