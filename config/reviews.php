<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\Enums\ReviewStatus;
use RoundlyConsulting\Reviews\Models\Review;

return [

    /*
    |--------------------------------------------------------------------------
    | Review Model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model used to persist reviews. Override this with your own
    | model (extending the package model) when you need custom behaviour.
    |
    */

    'model' => Review::class,

    /*
    |--------------------------------------------------------------------------
    | Rating Scale
    |--------------------------------------------------------------------------
    |
    | The inclusive integer range an explicit rating must fall within. Ratings
    | are optional (a review may be text only), but when supplied they are
    | validated against this range. Defaults to a classic 1–5 star scale.
    |
    */

    'min_rating' => (int) env('REVIEWS_MIN_RATING', 1),

    'max_rating' => (int) env('REVIEWS_MAX_RATING', 5),

    /*
    |--------------------------------------------------------------------------
    | Moderation
    |--------------------------------------------------------------------------
    |
    | "default_status" is the status a freshly created review receives.
    | "auto_approve" short-circuits moderation: new reviews are approved
    | immediately (and stamped with approved_at) when enabled.
    |
    */

    'default_status' => ReviewStatus::Pending->value,

    'auto_approve' => (bool) env('REVIEWS_AUTO_APPROVE', false),

    /*
    |--------------------------------------------------------------------------
    | Re-moderation on Edit
    |--------------------------------------------------------------------------
    |
    | When enabled, editing a review's rating or content sends it back to the
    | pending queue so the change can be re-moderated before it is shown again.
    |
    */

    'reset_status_on_edit' => (bool) env('REVIEWS_RESET_STATUS_ON_EDIT', true),

    /*
    |--------------------------------------------------------------------------
    | One Review Per Author
    |--------------------------------------------------------------------------
    |
    | When enabled, an author may only have one (non-deleted) review per
    | subject; a second attempt throws InvalidReviewException::duplicate().
    | Disabled by default so existing behaviour is unchanged.
    |
    */

    'one_per_author' => (bool) env('REVIEWS_ONE_PER_AUTHOR', false),

    /*
    |--------------------------------------------------------------------------
    | Facade Alias
    |--------------------------------------------------------------------------
    |
    | Registers a global "Reviews" alias for the package facade. Disable it if
    | the alias collides with another class in your application; the fully
    | qualified facade is always available regardless of this setting.
    |
    */

    'register_facade_alias' => (bool) env('REVIEWS_REGISTER_FACADE_ALIAS', true),

];
