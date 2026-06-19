<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Reviews\Enums\ReviewStatus;
use RoundlyConsulting\Reviews\Events\ReviewResponded;
use RoundlyConsulting\Reviews\Models\Review;

/**
 * Creates a response to a review. A response is itself a review row tied to the
 * parent via parent_id. Responses carry no rating, share the parent's
 * reviewable, and are approved immediately so they never sit in the moderation
 * queue (they are authored by the owner, not the public).
 */
final class RespondToReview
{
    public function execute(Review $parent, Model $author, string $content, ?string $title = null): Review
    {
        $response = $this->newReview();

        $response->parent_id = $parent->getKey();
        $response->title = $title;
        $response->content = $content;
        $response->rating = null;
        $response->status = ReviewStatus::Approved;
        $response->approved_at = CarbonImmutable::now();

        $response->author()->associate($author);

        if ($parent->reviewable_type !== null) {
            $response->reviewable_type = $parent->reviewable_type;
            $response->reviewable_id = $parent->reviewable_id;
        }

        $response->save();

        ReviewResponded::dispatch($parent, $response);

        return $response;
    }

    private function newReview(): Review
    {
        /** @var class-string<Review> $model */
        $model = config('reviews.model', Review::class);

        return new $model;
    }
}
