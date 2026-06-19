# Reviews for Laravel

A complete, batteries-included reviews and ratings system for Laravel. A polymorphic review
model lets any model in your application be both the **author** of a review and the
**reviewable** subject — so users can review products, businesses can review users, anything
can review anything — with star ratings, a moderation lifecycle, aggregates, query scopes, a
fluent facade, and model traits.

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

## Configuration

The published `config/reviews.php` file:

```php
<?php

use RoundlyConsulting\Reviews\Enums\ReviewStatus;
use RoundlyConsulting\Reviews\Models\Review;

return [

    // The Eloquent model used to persist reviews. Swap in your own model
    // (extending the package model) to add custom behaviour.
    'model' => Review::class,

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

];
```

| Key | Type | Default | Env | Purpose |
|---|---|---|---|---|
| `model` | `class-string` | `Review::class` | — | Model the package persists; extend it for custom behaviour. |
| `min_rating` | `int` | `1` | `REVIEWS_MIN_RATING` | Lowest allowed rating value. |
| `max_rating` | `int` | `5` | `REVIEWS_MAX_RATING` | Highest allowed rating value. |
| `default_status` | `string` | `pending` | — | Status a new review receives when not auto-approved. |
| `auto_approve` | `bool` | `false` | `REVIEWS_AUTO_APPROVE` | Approve new reviews immediately. |
| `reset_status_on_edit` | `bool` | `true` | `REVIEWS_RESET_STATUS_ON_EDIT` | Re-moderate a review when its rating/content changes. |
| `one_per_author` | `bool` | `false` | `REVIEWS_ONE_PER_AUTHOR` | Block a second review by the same author for the same subject. |
| `register_facade_alias` | `bool` | `true` | `REVIEWS_REGISTER_FACADE_ALIAS` | Register the global `Reviews` alias. |

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

### Using your own model

Point the `model` config key at a class that extends the package's `Review` model to add
relationships, scopes, or accessors:

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
