# Changelog

All notable changes to `reviews-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

### Added

- A publish-only migration, `cascade_review_responses_on_delete`, switches `parent_id` on the configured
  reviews table to cascade on delete, so a review deleted in the database (bypassing Eloquent) takes its
  owner responses with it. It works on installs that already ran `create_reviews_table` (MySQL, PostgreSQL
  and SQLite, rows kept). Upgrading: `php artisan vendor:publish --tag="reviews-migrations"`, then
  `php artisan migrate`.

### Changed

- `Reviews::update()` fires `ReviewVerified` (with the new state) when the update really changes the
  `verified` flag, like `verify()` / `unverify()`; `Reviews::fake()` records it as verified / unverified.
- Force-deleting a review purges its photos whatever `reviews.photos.enabled` says, so photos stored while
  the feature was on no longer outlive their review once it is switched off.
- `recountReviews()` writes only the two cached counter columns, like the vote tallies: it no longer
  bumps the reviewable's `updated_at` or saves its other unsaved changes.
- Requires `roundly-consulting/media-library-for-laravel` `^1.1.1`.

### Fixed

- Force-deleting a review force-deletes its owner responses (live and soft-deleted) through Eloquent,
  instead of leaving them behind as top-level approved reviews that counted towards the subject.
- The cached `reviews_count` / `reviews_avg` no longer lose a concurrent approved review: the recount
  runs in a transaction under the subject's row lock and counts with locking reads, and inside a
  transaction the subject is locked before the review row is written.
- Inside a host transaction under REPEATABLE READ (MySQL's default), the `one_per_author` re-check and
  the helpful / unhelpful tallies no longer miss a review or vote committed meanwhile: both are locking
  reads now.
- `WordListModerator` matches banned entries that contain a space or punctuation (`rip off`, `f*ck`) as
  consecutive words; an entry with no word in it is ignored.
- Creating a review with a `reviews.model` on its own connection runs the write and its photos in a
  transaction on that connection (media-library's nested inside), so a refused photo no longer leaves the
  review behind.
- A review with no rating needs content that is more than whitespace, on create and after an update.
- A freshly created review or response carries its column defaults in memory (pending, unverified, zero
  tallies): `ReviewResource` no longer returns `null` tallies, and `unverify()` on a fresh response no
  longer fires `ReviewVerified` (or throws under `Reviews::fake()`).
- `Reviews::for($subject)->summary()`, `photoCount()` and `reviewsWithPhotos()` no longer fail on a
  subject with more approved reviews than the database allows placeholders (32 766 on SQLite, 65 535 on
  PostgreSQL and MySQL).
- The review and vote factories build the configured `reviews.model` / `reviews.vote_model`, and a
  factory vote's parent review lands in the table the votes foreign key points at.
- The `create_reviews_table` migration creates the configured `reviews.model` table, with `parent_id`
  referencing that same table, and leaves a table that already exists alone. Hosts that already ran it
  keep their copy.
- A refused later photo no longer leaves the earlier photos' files (originals and variants) on disk:
  media-library 1.1.1 deletes the files an add wrote when the create transaction rolls back.

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
