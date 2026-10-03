<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\Enums\ReviewStatus;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Models\ReviewVote;
use RoundlyConsulting\Reviews\Moderation\NullModerator;

return [

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | The Eloquent models used to persist reviews and helpful votes. Override
    | either with your own model (extending the package model) when you need
    | custom behaviour.
    |
    */

    'model' => Review::class,

    'vote_model' => ReviewVote::class,

    /*
    |--------------------------------------------------------------------------
    | Key Type
    |--------------------------------------------------------------------------
    |
    | The key type used for the polymorphic reviewable / author / voter columns.
    | Use "uuid" or "ulid" when the models those columns point at use UUID/ULID
    | primary keys, otherwise leave it as "bigint". Your morph targets must share
    | one key type; set this to match. Any other value throws an
    | InvalidConfigurationException instead of quietly migrating as "bigint".
    |
    | Supported: "bigint", "uuid", "ulid"
    |
    */

    'key_type' => env('REVIEWS_KEY_TYPE', 'bigint'),

    /*
    |--------------------------------------------------------------------------
    | Rating Scale
    |--------------------------------------------------------------------------
    |
    | The inclusive integer range an explicit rating must fall within. Ratings
    | are optional (a review may be text only), but when supplied they are
    | validated against this range. Defaults to a classic 1–5 star scale.
    | min_rating must be 0–255 (the column is an unsigned tinyint) and
    | max_rating between min_rating (at least 1) and 255. Integers only: a
    | value such as "five" throws an InvalidConfigurationException.
    |
    */

    'min_rating' => env('REVIEWS_MIN_RATING', 1),

    'max_rating' => env('REVIEWS_MAX_RATING', 5),

    /*
    |--------------------------------------------------------------------------
    | Moderation
    |--------------------------------------------------------------------------
    |
    | "default_status" is the status a review lands in when the moderator
    | leaves it undecided. "auto_approve" short-circuits the moderator: new
    | (and re-moderated, edited) reviews are approved immediately and stamped
    | with approved_at. However a review lands approved or rejected, the
    | ReviewApproved / ReviewRejected event fires for it.
    |
    */

    'default_status' => ReviewStatus::Pending->value,

    'auto_approve' => env('REVIEWS_AUTO_APPROVE', false),

    /*
    |--------------------------------------------------------------------------
    | Re-moderation on Edit
    |--------------------------------------------------------------------------
    |
    | An edit that changes a review's rating, title or content is moderated
    | again: auto_approve, then the moderator. When enabled, an undecided
    | outcome sends the review back to "default_status" (the pending queue)
    | so a human sees the change before it is shown again; when disabled, an
    | undecided outcome keeps its status. Either way a moderator's approve or
    | reject applies. Rejected reviews stay rejected until approved explicitly,
    | and owner responses are never re-moderated.
    |
    */

    'reset_status_on_edit' => env('REVIEWS_RESET_STATUS_ON_EDIT', true),

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

    'one_per_author' => env('REVIEWS_ONE_PER_AUTHOR', false),

    /*
    |--------------------------------------------------------------------------
    | Facade Alias
    |--------------------------------------------------------------------------
    |
    | Registers a global "Reviews" alias for the package facade. Disable it
    | (false/0/off/no) if the alias collides with another class in your
    | application; the fully qualified facade is always available regardless of
    | this setting. Any other string is used as the alias name instead. A blank
    | value is not set, so the alias is registered.
    |
    */

    'register_facade_alias' => env('REVIEWS_REGISTER_FACADE_ALIAS', true),

    /*
    |--------------------------------------------------------------------------
    | Moderator
    |--------------------------------------------------------------------------
    |
    | The ReviewModerator implementation bound into the container and consulted
    | when a new (non force-approved) review is created, and again when an edit
    | changes its rating, title or content. The default no-op moderator leaves
    | reviews at their default status. Swap in WordListModerator (or your own)
    | to auto-approve/auto-reject. Banned words feed the bundled
    | WordListModerator and are matched case-insensitively as whole words, in
    | any script (accented and non-Latin words included).
    |
    */

    'moderator' => NullModerator::class,

    'moderation' => [
        'banned_words' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('REVIEWS_BANNED_WORDS', '')),
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cached Aggregates
    |--------------------------------------------------------------------------
    |
    | When enabled, reviewable models using the MaintainsReviewAggregates trait
    | have their reviews_count / reviews_avg columns kept in sync automatically
    | on every approved-review change. Publish the aggregate migration stub to
    | add those columns, then run "php artisan reviews:recount {Model}" to seed
    | existing rows.
    |
    */

    'cache_aggregates' => env('REVIEWS_CACHE_AGGREGATES', false),

    /*
    |--------------------------------------------------------------------------
    | Review Photos
    |--------------------------------------------------------------------------
    |
    | Reviews can carry a gallery of photos, stored through
    | roundly-consulting/media-library-for-laravel. Each review becomes a media
    | owner with a single "photos" bucket. Disable the whole feature with
    | "enabled" => false — the bucket is then never declared and the builder's
    | withPhoto()/withPhotos() helpers throw. Keys:
    |
    |   enabled              Master switch for the photos feature.
    |   bucket               The media bucket name photos are stored in.
    |   disk                 Storage disk for every photo. null = by visibility:
    |                        private photos go to private_disk, public ones to
    |                        the media-library default disk.
    |   private_disk         Non-public disk for PRIVATE photos (and their
    |                        variants) when disk is null. Never the web-served
    |                        'public' disk: a private photo there is reachable
    |                        under /storage without its signed URL.
    |   max                  Per-review photo limit (0 = unlimited); overflow
    |                        throws InvalidReviewException::tooManyPhotos().
    |   max_file_size        Largest accepted upload, in bytes (0 = no cap).
    |   accepted_mime_types  Whitelisted image mime types ([] accepts any).
    |   responsive_widths    Width ladder for responsive variants (null uses the
    |                        media-library default ladder).
    |   visibility           "public" (default) or "private".
    |   warm_on_approval     Queue variant generation when a review is approved.
    |
    | Every value is read strictly: a value that is not set (null, or blank
    | like a host's KEY=) takes the default. A non-integer count ("five"), a
    | negative one, a visibility typo ("privat") or a non-string bucket / disk
    | name throws an InvalidConfigurationException rather than falling back — a
    | junk max would otherwise read as 0 (unlimited), a junk visibility as public.
    |
    */

    'photos' => [

        'enabled' => env('REVIEWS_PHOTOS_ENABLED', true),

        'bucket' => env('REVIEWS_PHOTOS_BUCKET', 'photos'),

        'disk' => env('REVIEWS_PHOTOS_DISK'),

        'private_disk' => env('REVIEWS_PHOTOS_PRIVATE_DISK', 'local'),

        'max' => env('REVIEWS_PHOTOS_MAX', 5),

        'max_file_size' => env('REVIEWS_PHOTOS_MAX_FILE_SIZE', 5 * 1024 * 1024),

        'accepted_mime_types' => [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
        ],

        'responsive_widths' => [320, 640, 1024],

        'visibility' => env('REVIEWS_PHOTOS_VISIBILITY', 'public'),

        'warm_on_approval' => env('REVIEWS_PHOTOS_WARM_ON_APPROVAL', true),

    ],

];
