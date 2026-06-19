# Reviews for Laravel

A complete, batteries-included reviews and ratings system for Laravel. A polymorphic review
model lets any model in your application be both the **author** of a review and the
**reviewable** subject — so users can review products, businesses can review users, anything
can review anything — with star ratings, a moderation lifecycle, verified-purchase flags,
helpful votes, owner responses, cached aggregates, a pluggable moderator, query scopes, a
fluent facade, model traits, a JSON resource, and first-class testing helpers.

## Requirements

- PHP `^8.4`
- Laravel `^12.0` or `^13.0`

## Installation

Install the package via Composer:

```bash
composer require roundly-consulting/reviews-for-laravel
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag="reviews-migrations"
php artisan migrate
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
migration stub, rename its table, and migrate:

```bash
php artisan vendor:publish --tag="reviews-aggregate-migrations"
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

### Moderation lifecycle

Every review has a `ReviewStatus` of `Pending`, `Approved`, or `Rejected`. New reviews are
`pending` unless `auto_approve` is enabled.

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

`ratingSummary()` returns a `RatingSummary` DTO (`average`, `count`, `distribution`) with a
`toArray()` for JSON responses. The same aggregates are available on the facade:

```php
Reviews::averageFor($restaurant);
Reviews::countFor($restaurant);
Reviews::distributionFor($restaurant);
Reviews::summaryFor($restaurant);
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

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security
vulnerabilities.

## Credits

- [Andrej Mihaliak](https://github.com/mihaliak)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
