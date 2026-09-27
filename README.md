<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/reviews-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=reviews-for-laravel">
    <img src="art/hero.png" alt="Reviews for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

# Reviews for Laravel

A complete, batteries-included reviews and ratings system for Laravel. A polymorphic review
model lets any model in your application be both the **author** of a review and the
**reviewable** subject — so users can review products, businesses can review users, anything
can review anything — with star ratings, a moderation lifecycle, verified-purchase flags,
helpful votes, owner responses, cached aggregates, a pluggable moderator, query scopes, a
fluent facade, model traits, a JSON resource, and first-class testing helpers.

Reviews can also carry **photos** — a responsive image gallery per review, powered by
`media-library-for-laravel` — and its status enums adopt the shared `enums-for-laravel`
helpers.

## Integrates with

This package builds directly on two other roundly-consulting packages (both hard
dependencies, installed automatically):

- **[`media-library-for-laravel`](https://github.com/roundly-consulting/media-library-for-laravel)** —
  powers **review photos**. The bundled `Review` is a media owner with a config-driven `photos`
  bucket: responsive image variants, an ordered gallery, a per-review limit, fluent builder attach
  (`withPhoto()`/`withPhotos()`), photo counts on the rating summary, warm-on-approval, and
  cleanup on force-delete. Disable it entirely with `reviews.photos.enabled = false`.
- **[`enums-for-laravel`](https://github.com/roundly-consulting/enums-for-laravel)** —
  `ReviewStatus` and `ModerationDecision` use the `RoundlyConsulting\Enums\Helpers` trait, adding
  `values()`, `labels()`, `options()`, `toOptions()`, `validationRule()`, `readable()`, and case
  lookups on top of their domain methods.

## Requirements

- PHP `^8.4`
- Laravel `^12.0` or `^13.0`
- `roundly-consulting/media-library-for-laravel` and `roundly-consulting/enums-for-laravel`
  (pulled in automatically)

## Installation

Install the package via Composer:

```bash
composer require roundly-consulting/reviews-for-laravel
```

Publish and run the migrations. The package's migrations are **publish-only** — nothing is
auto-loaded, so a bare `php artisan migrate` will not create the `reviews` tables until you
have published them:

```bash
php artisan vendor:publish --tag="reviews-migrations"
php artisan migrate
```

Review photos are stored through `media-library-for-laravel`, whose migration is published the
same way:

```bash
php artisan vendor:publish --tag="media-migrations"
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="reviews-config"
```

Optionally publish the translations:

```bash
php artisan vendor:publish --tag="reviews-translations"
```

Optionally publish the JSON resources (to customise the exposed API shape):

```bash
php artisan vendor:publish --tag="reviews-resources"
```

If you want **cached aggregates** on a reviewable table, publish the aggregate-columns
migration stub, rename its placeholder table, and migrate. It has its own tag (it is opt-in,
and you publish it once per reviewable table):

```bash
php artisan vendor:publish --tag="reviews-aggregate-migrations"
php artisan migrate
```

## Configuration

The published `config/reviews.php` file:

```php
<?php

use RoundlyConsulting\Reviews\Enums\ReviewStatus;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Models\ReviewVote;
use RoundlyConsulting\Reviews\Moderation\NullModerator;

return [

    // The Eloquent models used to persist reviews and helpful votes.
    'model' => Review::class,
    'vote_model' => ReviewVote::class,

    // The inclusive integer range an explicit rating must fall within.
    'min_rating' => (int) env('REVIEWS_MIN_RATING', 1),
    'max_rating' => (int) env('REVIEWS_MAX_RATING', 5),

    // The status a new review receives, and whether to approve immediately.
    'default_status' => ReviewStatus::Pending->value,
    'auto_approve' => (bool) env('REVIEWS_AUTO_APPROVE', false),

    // Send a review back to "pending" when its rating or content is edited.
    'reset_status_on_edit' => (bool) env('REVIEWS_RESET_STATUS_ON_EDIT', true),

    // Allow at most one (non-deleted) review per author per subject.
    'one_per_author' => (bool) env('REVIEWS_ONE_PER_AUTHOR', false),

    // Register a global "Reviews" facade alias.
    'register_facade_alias' => (bool) env('REVIEWS_REGISTER_FACADE_ALIAS', true),

    // The ReviewModerator consulted when a new review is created.
    'moderator' => NullModerator::class,
    'moderation' => [
        'banned_words' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('REVIEWS_BANNED_WORDS', '')),
        ))),
    ],

    // Keep reviews_count / reviews_avg in sync on reviewables that opt in.
    'cache_aggregates' => (bool) env('REVIEWS_CACHE_AGGREGATES', false),

    // Review photos (media-library-for-laravel). Set enabled = false to switch the
    // whole feature off — the bucket is then never declared and withPhoto() throws.
    'photos' => [
        'enabled' => (bool) env('REVIEWS_PHOTOS_ENABLED', true),
        'bucket' => env('REVIEWS_PHOTOS_BUCKET', 'photos'),
        'disk' => env('REVIEWS_PHOTOS_DISK'),
        'private_disk' => env('REVIEWS_PHOTOS_PRIVATE_DISK', 'local'),
        'max' => (int) env('REVIEWS_PHOTOS_MAX', 5),
        'max_file_size' => (int) env('REVIEWS_PHOTOS_MAX_FILE_SIZE', 5 * 1024 * 1024),
        'accepted_mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
        'responsive_widths' => [320, 640, 1024],
        'visibility' => env('REVIEWS_PHOTOS_VISIBILITY', 'public'),
        'warm_on_approval' => (bool) env('REVIEWS_PHOTOS_WARM_ON_APPROVAL', true),
    ],

];
```

| Key | Type | Default | Env | Purpose |
|---|---|---|---|---|
| `model` | `class-string` | `Review::class` | — | Model the package persists; extend it for custom behaviour. |
| `vote_model` | `class-string` | `ReviewVote::class` | — | Model used for helpful votes; extend it for custom behaviour. |
| `min_rating` | `int` | `1` | `REVIEWS_MIN_RATING` | Lowest allowed rating value. |
| `max_rating` | `int` | `5` | `REVIEWS_MAX_RATING` | Highest allowed rating value. |
| `default_status` | `string` | `pending` | — | Status a new review receives when not auto-approved. |
| `auto_approve` | `bool` | `false` | `REVIEWS_AUTO_APPROVE` | Approve new reviews immediately. |
| `reset_status_on_edit` | `bool` | `true` | `REVIEWS_RESET_STATUS_ON_EDIT` | Re-moderate a review when its rating/content changes. |
| `one_per_author` | `bool` | `false` | `REVIEWS_ONE_PER_AUTHOR` | Block a second review by the same author for the same subject. |
| `register_facade_alias` | `bool` | `true` | `REVIEWS_REGISTER_FACADE_ALIAS` | Register the global `Reviews` alias. |
| `moderator` | `class-string` | `NullModerator::class` | — | `ReviewModerator` consulted on create; swap in `WordListModerator` or your own. |
| `moderation.banned_words` | `list<string>` | `[]` | `REVIEWS_BANNED_WORDS` | Comma-separated words the `WordListModerator` rejects on. |
| `cache_aggregates` | `bool` | `false` | `REVIEWS_CACHE_AGGREGATES` | Maintain cached `reviews_count` / `reviews_avg` on opted-in reviewables. |
| `photos.enabled` | `bool` | `true` | `REVIEWS_PHOTOS_ENABLED` | Master switch for review photos; when `false`, `withPhoto()` throws and the bucket is never declared. |
| `photos.bucket` | `string` | `photos` | `REVIEWS_PHOTOS_BUCKET` | The media bucket photos are stored in. |
| `photos.disk` | `?string` | `null` | `REVIEWS_PHOTOS_DISK` | Storage disk for every photo. `null` = by visibility: private → `photos.private_disk`, public → media-library's default disk. |
| `photos.private_disk` | `string` | `local` | `REVIEWS_PHOTOS_PRIVATE_DISK` | Non-public disk for private photos (and their variants) when `photos.disk` is `null`. |
| `photos.max` | `int` | `5` | `REVIEWS_PHOTOS_MAX` | Per-review photo limit (`0` = unlimited); overflow throws `tooManyPhotos()`. |
| `photos.max_file_size` | `int` | `5242880` | `REVIEWS_PHOTOS_MAX_FILE_SIZE` | Largest accepted upload, in bytes. |
| `photos.accepted_mime_types` | `list<string>` | image types | — | Whitelisted photo mime types. |
| `photos.responsive_widths` | `list<int>` | `[320, 640, 1024]` | — | Responsive variant width ladder (`null` uses the media default). |
| `photos.visibility` | `string` | `public` | `REVIEWS_PHOTOS_VISIBILITY` | `public` or `private`. |
| `photos.warm_on_approval` | `bool` | `true` | `REVIEWS_PHOTOS_WARM_ON_APPROVAL` | Queue variant generation when a review is approved. |

The package works with zero configuration; every key above has a sensible default.

## Usage

### Add the traits to your models

Mark a subject as reviewable and an author as a reviewer with one trait each:

```php
use RoundlyConsulting\Reviews\Concerns\CanReview;
use RoundlyConsulting\Reviews\Concerns\HasReviews;

class Restaurant extends Model
{
    use HasReviews;
}

class User extends Model
{
    use CanReview;
}
```

### Create a review (fluent facade)

```php
use RoundlyConsulting\Reviews\Facades\Reviews;

$review = Reviews::for($restaurant)   // the reviewable subject
    ->by($user)                       // the author
    ->rating(5)
    ->title('Loved it')
    ->content('Great place')
    ->meta(['visit' => 'dinner'])     // array or Collection
    ->create();

// Rating-only (no text):
Reviews::for($restaurant)->by($user)->rating(4)->create();

// Force-approve on create (bypasses moderation):
Reviews::for($restaurant)->by($user)->content('Trusted')->approved()->create();

// Mark a verified purchase / verified reviewer:
Reviews::for($restaurant)->by($user)->rating(5)->verified()->create();
```

A review must have **either** a rating **or** content — an empty review throws
`InvalidReviewException`. A rating outside the configured range throws
`InvalidRatingException`.

The underlying `CreateReview` action and `CreateReviewData` DTO remain available if you prefer
to call them directly:

```php
use RoundlyConsulting\Reviews\Actions\CreateReview;
use RoundlyConsulting\Reviews\DataTransferObjects\CreateReviewData;

$review = app(CreateReview::class)->execute(new CreateReviewData(
    author: $user,
    reviewable: $restaurant,
    content: 'Great place',
    title: 'Loved it',
    rating: 5,
    meta: collect(['visit' => 'dinner']),
));
```

### Review photos

Attach photos to a review straight from the builder. Photos accept an `UploadedFile`, a
media-library **draft token**, a disk path, or a URL, and are bound to the review inside the
create transaction — **before** `ReviewCreated` fires — so listeners and broadcasts see them.

```php
$review = Reviews::for($restaurant)
    ->by($user)
    ->rating(5)
    ->content('Great place')
    ->withPhoto($request->file('photo'))          // one UploadedFile
    ->withPhotos($request->file('photos'))        // an array of files
    ->withDraftPhoto($token)                       // a media-library draft token
    ->withPhotoFromDisk('incoming/a.jpg', 's3')    // a path on a disk
    ->withPhotoFromUrl('https://example.com/a.jpg')
    ->create();
```

Read them back with the readers the bundled `Review` gains:

```php
$review->photos();                 // Collection<Media> (ordered gallery)
$review->hasPhotos();              // bool
$review->photoCount();             // int
$review->firstPhotoUrl();          // string ('' when empty)
$review->firstPhotoUrl('thumb');   // a named responsive variant
$review->photoUrls();              // list<string>
$review->responsivePhotos(['class' => 'photo']); // list<string> of <img srcset="…">
$review->resolvePhotoUrl($media);  // one photo's URL
$review->photoSrcset($media);      // one photo's srcset
$review->firstPhotoTemporaryUrl(); // always a signed URL
```

Every reader resolves each photo by its visibility: public photos get their public (CDN-able)
URLs, while with `photos.visibility = private` every URL — including `srcset` entries,
`responsivePhotos()` and `ReviewResource` — is a short-lived signed URL, never a public one.
Private photos (and their variants) are stored on `photos.private_disk` — Laravel's non-public
`local` disk by default — never on media-library's web-served `public` disk, so the signed URL
is the only way in. An explicit `photos.disk` is used for every photo, so keep it non-public
while photos are private.

The `photos` bucket is **public** by default, holds up to `photos.max` (default **5**) images,
and produces responsive variants for the configured `responsive_widths`. Exceeding the limit
throws `InvalidReviewException::tooManyPhotos()`. When `photos.enabled` is `false`, the bucket is
never declared and `withPhoto()` throws `InvalidReviewException::photosDisabled()`.

Photos are warmed on approval (a queued variant-generation job per photo, via
`ReviewApproved`) and cleaned up when a review is **force-deleted** — a soft-deleted (and later
restored) review keeps its photos. The rating summary also reports `photoCount` and
`reviewsWithPhotos` across a subject's approved reviews (see below), and `ReviewResource`
exposes a `photos` array of `{ id, url, srcset }`.

### Moderation lifecycle

Every review has a `ReviewStatus` of `Pending`, `Approved`, or `Rejected`. New reviews are
`pending` unless `auto_approve` is enabled.

`ReviewStatus` and `ModerationDecision` adopt the `enums-for-laravel` helpers, so you get
`ReviewStatus::values()`, `->labels()`, `->options()`, `->toOptions()`, `->validationRule()`
(`in:pending,approved,rejected`), and `$status->readable()` for free.

```php
Reviews::approve($review);            // marks approved, stamps approved_at
Reviews::reject($review, 'Spam');     // marks rejected, stores the reason in meta

$review->isPending();
$review->isApproved();
$review->isRejected();
```

Approve/reject are idempotent. Each fires an event (see below).

### Update & delete

```php
use RoundlyConsulting\Reviews\DataTransferObjects\UpdateReviewData;

// Only the provided fields change; null means "leave unchanged".
Reviews::update($review, new UpdateReviewData(content: 'Edited', rating: 4));

Reviews::delete($review);             // soft-deletes
```

When `reset_status_on_edit` is enabled (default), editing the rating or content sends an
approved review back to `pending` for re-moderation.

### Ratings & aggregates

All aggregates count **approved** reviews only.

```php
$restaurant->reviews;                 // MorphMany (all)
$restaurant->approvedReviews;         // approved only
$restaurant->averageRating();         // ?float, null when none
$restaurant->reviewsCount();          // int (all)
$restaurant->approvedReviewsCount();  // int (approved)
$restaurant->ratingDistribution();    // [5 => 120, 4 => 80, ...]
$restaurant->ratingSummary();         // RatingSummary DTO

// Add a review from the subject:
$restaurant->addReview($user)->rating(5)->content('...')->create();
```

`ratingSummary()` returns a `RatingSummary` DTO (`average`, `count`, `distribution`,
`photoCount`, `reviewsWithPhotos`) with a `toArray()` for JSON responses. The same aggregates
are available on the facade:

```php
Reviews::averageFor($restaurant);
Reviews::countFor($restaurant);
Reviews::distributionFor($restaurant);
Reviews::summaryFor($restaurant);
Reviews::photoCountFor($restaurant);         // total photos on approved reviews
Reviews::reviewsWithPhotosFor($restaurant);  // approved reviews that carry a photo
```

### Authoring lookups

```php
$user->reviewsAuthored;               // MorphMany
$user->hasReviewed($restaurant);      // bool
$user->reviewFor($restaurant);        // ?Review
```

### Query scopes

The `Review` model ships expressive scopes:

```php
use RoundlyConsulting\Reviews\Models\Review;

Review::query()->approved()->latestFirst()->get();
Review::query()->pending()->get();
Review::query()->rejected()->get();
Review::query()->rated()->get();                  // has a rating
Review::query()->withRatingOf(5)->get();
Review::query()->minRating(4)->get();
Review::query()->authoredBy($user)->get();
Review::query()->for($restaurant)->get();
Review::query()->verified()->get();
Review::query()->unverified()->get();
Review::query()->topLevel()->get();               // excludes owner responses
Review::query()->responses()->get();              // only owner responses
Review::query()->mostHelpful()->get();            // by net helpful score
```

### Verified reviews

A `verified` boolean marks verified purchases / verified reviewers. Set it on create with
`->verified()`, in an `UpdateReviewData(verified: true)`, or with the model helpers:

```php
$review->markVerified();
$review->markUnverified();
```

### Helpful votes

Any model can vote a review helpful or unhelpful. There is one vote per voter per review;
re-voting flips it. The review's `helpful_count` / `unhelpful_count` are kept denormalized.

```php
use RoundlyConsulting\Reviews\Facades\Reviews;

Reviews::vote($review, $user, true);    // helpful
Reviews::vote($review, $user, false);   // flips the same voter to unhelpful
Reviews::removeVote($review, $user);    // withdraw

$review->helpfulScore();                // helpful_count - unhelpful_count
```

Add the `CanVoteOnReviews` trait to a voter model for ergonomic helpers:

```php
use RoundlyConsulting\Reviews\Concerns\CanVoteOnReviews;

class User extends Model { use CanVoteOnReviews; }

$user->voteOn($review);                 // helpful by default
$user->hasVotedOn($review);             // bool
$user->votedHelpfulOn($review);         // bool
$user->removeVoteFrom($review);
```

### Owner responses

The reviewable's owner (or anyone) can respond to a review. A response is itself a review row
tied to its parent via `parent_id`, carries no rating, shares the parent's reviewable, and is
approved immediately — so responses never sit in the moderation queue and never count toward
ratings or aggregates.

```php
$response = Reviews::respond($review, $owner, 'Thanks for the feedback!');
// or:
$response = $review->respond($owner, 'Thanks for the feedback!');

$review->responses;       // HasMany of responses
$response->parent;        // BelongsTo back to the original review
$response->isResponse();  // true
```

### Cached aggregates

Live aggregates run queries on demand. For high-traffic reviewables you can cache
`reviews_count` and `reviews_avg` on the reviewable's own table and have the package keep them
in sync.

1. Set `reviews.cache_aggregates` to `true`.
2. Add the columns — publish the `reviews-aggregate-migrations` stub, rename its table to your
   reviewable's table, and migrate.
3. Add the `MaintainsReviewAggregates` trait (alongside `HasReviews`) to that model.

```php
use RoundlyConsulting\Reviews\Concerns\HasReviews;
use RoundlyConsulting\Reviews\Concerns\MaintainsReviewAggregates;

class Product extends Model
{
    use HasReviews;
    use MaintainsReviewAggregates;
}
```

The counters then update on every approved-review create/update/delete/approve/reject (the
trait respects the same "approved, top-level only" semantics as the live aggregates). Rebuild
them from scratch at any time:

```bash
php artisan reviews:recount "App\Models\Product"
```

### Reacting to events

Each action dispatches an event carrying the affected review, so you can react without
modifying the package:

| Event | Dispatched when |
|---|---|
| `ReviewCreated` | a review is created |
| `ReviewApproved` | a review is approved |
| `ReviewRejected` | a review is rejected (carries `?string $reason`) |
| `ReviewUpdated` | a review is updated |
| `ReviewDeleted` | a review is soft-deleted |
| `ReviewResponded` | an owner response is created (carries `parent` and `response`) |
| `ReviewVoted` | a helpful vote is cast or flipped (carries the `vote`) |
| `ReviewVoteRemoved` | a helpful vote is withdrawn (carries the `voter`) |

```php
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Reviews\Events\ReviewCreated;

Event::listen(function (ReviewCreated $event): void {
    logger()->info('New review', ['id' => $event->review->id]);
});
```

### Exceptions

All package exceptions extend `RoundlyConsulting\Reviews\Exceptions\ReviewException`:

- `InvalidReviewException` — empty review, or a duplicate when `one_per_author` is enabled.
- `InvalidRatingException` — a rating outside the configured range.

### Auto-moderation

New reviews (that aren't force-approved) are passed through the configured `ReviewModerator`.
The default `NullModerator` leaves them at the default status. The bundled, dependency-free
`WordListModerator` auto-rejects reviews containing any banned word (whole-word,
case-insensitive):

```php
// config/reviews.php
'moderator' => RoundlyConsulting\Reviews\Moderation\WordListModerator::class,
'moderation' => ['banned_words' => ['spam', 'scam']],
```

Write your own by implementing the contract and binding it via the `moderator` config key:

```php
use RoundlyConsulting\Reviews\Contracts\ReviewModerator;
use RoundlyConsulting\Reviews\DataTransferObjects\ModerationOutcome;
use RoundlyConsulting\Reviews\Models\Review;

final class MyModerator implements ReviewModerator
{
    public function moderate(Review $review): ModerationOutcome
    {
        return ModerationOutcome::approve();    // or ::reject('reason') / ::pending()
    }
}
```

### JSON resource

A publishable `ReviewResource` (and `ReviewCollection`) exposes a sensible public shape — id,
rating, status, verified, helpful counts, author/reviewable identifiers, and timestamps:

```php
use RoundlyConsulting\Reviews\Http\Resources\ReviewResource;

return ReviewResource::collection($product->approvedReviews()->with('responses')->get());
```

Publish them with `--tag="reviews-resources"` (copied to `app/Http/Resources/Reviews`) to
customise the fields.

### Custom verbs (macros)

The `Reviews` manager is `Macroable`, so host apps can register their own facade verbs:

```php
use RoundlyConsulting\Reviews\Facades\Reviews;

Reviews::macro('flagged', fn () => Review::query()->rejected()->get());

Reviews::flagged();
```

### Testing

Swap in a recording fake — operations still run, while assertions verify intent:

```php
use RoundlyConsulting\Reviews\Facades\Reviews;

$fake = Reviews::fake();

Reviews::for($product)->by($user)->rating(5)->create();

$fake->assertReviewCreated();
$fake->assertReviewCreated(fn ($review) => $review->rating === 5);
$fake->assertReviewApproved();
$fake->assertReviewRejected();
$fake->assertNothingReviewed();
```

Register the Pest expectation matchers in your `tests/Pest.php`:

```php
RoundlyConsulting\Reviews\Testing\ReviewExpectations::register();

expect($product)->toHaveReview();
expect($product)->toHaveReview($author);
expect($product)->toHaveApprovedReview();
```

Or mix the `InteractsWithReviews` trait into a test case for `actingAsReviewer()` /
`reviewAs()` ergonomics.

### Using your own model

Point the `model` (or `vote_model`) config key at a class that extends the package's model to
add relationships, scopes, or accessors:

```php
// config/reviews.php
'model' => \App\Models\Review::class,
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](https://github.com/roundly-consulting/.github/blob/main/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security
vulnerabilities.

## Credits

- [Andrej Mihaliak](https://github.com/mihaliak)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
