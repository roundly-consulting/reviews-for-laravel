<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/reviews-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=reviews-for-laravel">
    <img src="art/hero.png" alt="Reviews for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

<!-- roundly-badges:start -->
<p align="center">
  <a href="https://packagist.org/packages/roundly-consulting/reviews-for-laravel"><img src="https://img.shields.io/packagist/v/roundly-consulting/reviews-for-laravel?style=flat-square&label=release" alt="Latest release"></a>
  <a href="https://github.com/roundly-consulting/reviews-for-laravel/actions/workflows/run-tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/reviews-for-laravel/run-tests.yml?branch=main&style=flat-square&label=tests" alt="Tests"></a>
  <a href="https://github.com/roundly-consulting/reviews-for-laravel/actions/workflows/fix-php-code-style-issues.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/reviews-for-laravel/fix-php-code-style-issues.yml?branch=main&style=flat-square&label=code%20style" alt="Code style"></a>
  <a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/donate-support%20our%20open%20source-F24E29?style=flat-square&logo=stripe&logoColor=white" alt="Donate"></a>
  <a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/patreon-become%20a%20patron-F96854?style=flat-square&logo=patreon&logoColor=white" alt="Patreon"></a>
  <a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=reviews-for-laravel#crypto"><img src="https://img.shields.io/badge/crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=flat-square&logo=bitcoin&logoColor=white" alt="Crypto"></a>
</p>
<!-- roundly-badges:end -->

# Reviews for Laravel

Ratings and reviews for any Eloquent model — users review products, businesses review users,
anything can review anything. Star ratings, a moderation lifecycle, verified purchases, helpful
votes, owner responses, review photos and cached aggregates, behind one fluent facade.

## Installation

Requires PHP 8.4 and Laravel 12 or 13.

```bash
composer require roundly-consulting/reviews-for-laravel
php artisan vendor:publish --tag="reviews-migrations"
php artisan vendor:publish --tag="media-migrations"   # review photos live in media-library
php artisan migrate
```

If the models being reviewed, writing reviews or voting have UUID/ULID keys, set
`REVIEWS_KEY_TYPE=uuid` (or `ulid`) **before** migrating.

## Usage

Make a model reviewable and another one a reviewer:

```php
use RoundlyConsulting\Reviews\Concerns\CanReview;
use RoundlyConsulting\Reviews\Concerns\HasReviews;

class Product extends Model
{
    use HasReviews;
}

class User extends Authenticatable
{
    use CanReview;
}
```

Then write, moderate and read reviews:

```php
use RoundlyConsulting\Reviews\Facades\Reviews;

$review = Reviews::for($product)->by($user)
    ->rating(5)
    ->title('Loved it')
    ->content('Great value for the price')
    ->verified()                                    // a verified purchase
    ->create();                                     // lands as pending

Reviews::approve($review);
Reviews::respond($review, $owner, 'Thanks for the feedback!');
Reviews::vote($review, $shopper, helpful: true);

Reviews::for($product)->average();                  // 5.0
Reviews::for($product)->distribution();             // [5 => 1]
Reviews::for($product)->query()->approved()->mostHelpful()->paginate();
```

<!-- roundly-docs:start -->
## Documentation

The full documentation — configuration, every feature and its API, and testing — lives on our
website: **[roundly-consulting.com/open-source/docs/reviews-for-laravel](https://roundly-consulting.com/open-source/docs/reviews-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=reviews-for-laravel)**

Release notes are in [CHANGELOG.md](CHANGELOG.md). To contribute, see the
[contributing guide](https://github.com/roundly-consulting/.github/blob/main/CONTRIBUTING.md).
<!-- roundly-docs:end -->

<!-- roundly-support:start -->
## Support our work

This package is free and open source, built and maintained by
[Roundly Consulting](https://roundly-consulting.com/open-source?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=reviews-for-laravel).
If it saves you time, please consider supporting our open-source work — a one-time donation, a
monthly pledge on Patreon or a crypto donation helps fund maintenance, new features and new
packages.

<a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/Donate-Support%20Roundly%20open%20source-F24E29?style=for-the-badge&logo=stripe&logoColor=white" alt="Donate to Roundly open source"></a>
<a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/Patreon-Become%20a%20patron-F96854?style=for-the-badge&logo=patreon&logoColor=white" alt="Become a patron on Patreon"></a>
<a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=reviews-for-laravel#crypto"><img src="https://img.shields.io/badge/Crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=for-the-badge&logo=bitcoin&logoColor=white" alt="Donate crypto: BTC, ETH, BNB or SOL"></a>
<!-- roundly-support:end -->

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
