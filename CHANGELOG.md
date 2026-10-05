# Changelog

All notable changes to `reviews-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

## 1.0.3 - 2026-10-05

### Changed

- Documentation: the README hero image uses an absolute URL, so it also renders on Packagist and other sites.

### Fixed

- The `messages.photos.too_many` plural line also covers a count of 0, so it never renders with a leading space.

## 1.0.2 - 2026-10-04

### Fixed

- The `InvalidReviewException::tooManyPhotos()` and `photosDisabled()` messages are now translated
  (`reviews::messages.photos.*`, English and Slovak), and the photo limit uses proper plural forms
  instead of "photo(s)".

## 1.0.1 - 2026-10-04

### Changed

- Maintenance: `composer.json` `homepage` and `support.docs` now point to the documentation site.

### Fixed

- Slovak (`sk`) translations now ship alongside English for every language file.

## 1.0.0 - 2026-10-03

Initial public release.

### Added

- Polymorphic reviews: any model can review any other, via the `CanReview` and `HasReviews` traits.
- A fluent `Reviews` facade (`Reviews::for($product)->by($user)->rating(5)->content(...)->create()`)
  with star ratings in a configurable range, backed by an injectable `ReviewsManager` and one
  action per operation — facade, dependency injection and the raw action run the same code.
- `Reviews::for($subject)` scope: `by()`, `summary()`, `average()`, `count()`, `distribution()`,
  `photoCount()`, `reviewsWithPhotos()` and a listing `query()`; `Reviews::query()` for a
  swap-aware moderation queue; `Reviews::create(CreateReviewData)`.
- Review photos on `media-library-for-laravel`: `withPhoto()` / `withPhotos()` on the builder,
  responsive variants, a per-review limit and signed URLs for private photos.
- A moderation lifecycle (`Pending`, `Approved`, `Rejected`) with `Reviews::approve()` /
  `reject()` and optional re-moderation after edits.
- Pluggable auto-moderation through the `ReviewModerator` contract, with a bundled
  `WordListModerator`.
- Rating aggregates: `averageRating()`, `ratingDistribution()` and a `RatingSummary` DTO, plus
  optional cached counters with `MaintainsReviewAggregates` and `reviews:recount`.
- Verified-review flags with `Reviews::verify()` / `unverify()` (the `MarkReviewVerified`
  action and a `ReviewVerified` event), helpful / unhelpful votes (`Reviews::vote()`, `CanVoteOnReviews`) and
  owner responses (`Reviews::respond()`).
- Query scopes such as `approved()`, `minRating()`, `verified()`, `authoredBy()` and
  `mostHelpful()`.
- Events for every action: created, approved, rejected, updated, deleted, responded, voted,
  vote removed and verified.
- A publishable `ReviewResource`, a `Macroable` manager for custom verbs, and `Reviews::fake()`:
  a recording `ReviewsFake` that sees every mutation (including `$review->respond()`,
  `markVerified()` and the voting / reviewable traits) with an `assert*` / `assertNothing*` pair
  for create, approve, reject, update, delete, respond, vote, vote removal, verify and unverify.
