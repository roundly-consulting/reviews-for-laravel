<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Support\ReviewsConfig;

/**
 * Public-facing JSON shape for a review. To customise the exposed fields, publish
 * the "reviews-resources" tag: it writes an editable copy into
 * App\Http\Resources\Reviews (stubs/ReviewResource.php.stub, kept in step
 * with this class by tests/Feature/Provider/PublishedResourcesTest.php).
 *
 * `author` and `reviewable` render through the `reviews.resources.author` /
 * `reviews.resources.reviewable` resources (id + class basename by default, never a class
 * name), or `null` once the related row is gone. Eager-load `author` and `reviewable` when
 * rendering many reviews yourself; ReviewCollection does it for you.
 *
 * @mixin Review
 */
final class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Review $review */
        $review = $this->resource;

        return [
            'id' => $review->id,
            'parent_id' => $review->parent_id,
            'rating' => $review->rating,
            'status' => $review->status->value,
            'verified' => $review->verified,
            'title' => $review->title,
            'content' => $review->content,
            'helpful_count' => $review->helpful_count,
            'unhelpful_count' => $review->unhelpful_count,
            'helpful_score' => $review->helpfulScore(),
            'author' => self::authorOf($review, $request),
            'reviewable' => self::reviewableOf($review, $request),
            // One query per morph type for the responses' authors and subjects, not one per response.
            'responses' => ReviewResource::collection($this->whenLoaded(
                'responses',
                static fn (): mixed => $review->responses->loadMissing(['author', 'reviewable']),
            )),
            'photos' => $this->photos($review),
            'created_at' => $review->created_at?->toIso8601String(),
            'updated_at' => $review->updated_at?->toIso8601String(),
            'approved_at' => $review->approved_at?->toIso8601String(),
        ];
    }

    /**
     * The review's author rendered through the configured `reviews.resources.author` resource,
     * or `null` when the author row is gone.
     *
     * @return array<array-key, mixed>|null
     */
    public static function authorOf(Review $review, Request $request): ?array
    {
        return self::reference(ReviewsConfig::authorResource(), $review->author, $request);
    }

    /**
     * The review's subject rendered through the configured `reviews.resources.reviewable`
     * resource, or `null` when the subject row is gone.
     *
     * @return array<array-key, mixed>|null
     */
    public static function reviewableOf(Review $review, Request $request): ?array
    {
        return self::reference(ReviewsConfig::reviewableResource(), $review->reviewable, $request);
    }

    /**
     * Resolved here, with the parent's request, so `toArray()` hands PHP callers plain arrays.
     *
     * @param  class-string<JsonResource>  $resource
     * @return array<array-key, mixed>|null
     */
    private static function reference(string $resource, ?Model $model, Request $request): ?array
    {
        return $model === null ? null : $resource::make($model)->resolve($request);
    }

    /**
     * The review's photos as `{id, url, srcset}` entries, or `[]` when photos are disabled
     * or the bucket is empty.
     *
     * @return list<array{id: int|string|null, url: string, srcset: string}>
     */
    private function photos(Review $review): array
    {
        $photos = [];

        foreach ($review->photos() as $media) {
            $photos[] = [
                'id' => $media->getKey(),
                // Signed for a private photo, never its public URL.
                'url' => $review->resolvePhotoUrl($media),
                'srcset' => $review->photoSrcset($media),
            ];
        }

        return $photos;
    }
}
