# Reviews for Laravel

Write reviews to any entity from any entity. A polymorphic review model lets any model in
your application be both the **author** of a review and the **reviewable** subject — so users
can review products, businesses can review users, anything can review anything.

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

## Configuration

The published `config/reviews.php` file exposes a single key:

```php
<?php

use RoundlyConsulting\Reviews\Models\Review;

return [

    // The Eloquent model used to persist reviews. Swap in your own model
    // (extending the package model) to add custom behaviour.
    'model' => Review::class,

];
```

| Key     | Type     | Default          | Purpose                                            |
|---------|----------|------------------|----------------------------------------------------|
| `model` | `string` | `Review::class`  | The model class the `CreateReview` action persists. |

## Usage

### Creating a review

Build a `CreateReviewData` DTO and pass it to the `CreateReview` action. The `author` and
`reviewable` can be **any** Eloquent model — they are stored polymorphically.

```php
use RoundlyConsulting\Reviews\Actions\CreateReview;
use RoundlyConsulting\Reviews\DataTransferObjects\CreateReviewData;

$review = app(CreateReview::class)->execute(new CreateReviewData(
    author: $user,            // any Eloquent model
    reviewable: $restaurant,  // any Eloquent model
    content: 'Hello, this is my review.',
    title: 'Very nice place',           // optional
    meta: collect(['staff' => 'nice']), // optional, stored as JSON
));
```

`title` and `meta` are optional. `meta` is cast to an `Illuminate\Support\Collection` and
persisted as JSON.

### Reacting to new reviews

Each successful `CreateReview::execute()` dispatches a `ReviewCreated` event carrying the
saved review, so you can react without modifying the package:

```php
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Reviews\Events\ReviewCreated;

Event::listen(function (ReviewCreated $event): void {
    // $event->review is the freshly created Review
    logger()->info('New review', ['id' => $event->review->id]);
});
```

### Accessing the relations

```php
$review->author;      // the model that wrote the review
$review->reviewable;  // the model that was reviewed
```

### Using your own model

Point the `model` config key at a class that extends the package's `Review` model to add
relationships, scopes, or accessors. The `CreateReview` action will persist your class:

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
