<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use RoundlyConsulting\Reviews\Database\Factories\ReviewFactory;

/**
 * @property int $id
 * @property string|null $reviewable_type
 * @property int|null $reviewable_id
 * @property string|null $author_type
 * @property int|null $author_id
 * @property string|null $title
 * @property string $content
 * @property Collection<string, mixed>|null $meta
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 */
class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'meta' => 'collection',
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

    protected static function newFactory(): ReviewFactory
    {
        return ReviewFactory::new();
    }
}
