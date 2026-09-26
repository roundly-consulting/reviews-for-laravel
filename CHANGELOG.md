# Changelog

All notable changes to `reviews-for-laravel` will be documented in this file.

## Unreleased

### Fixed

- With `photos.visibility = private` (a documented option), every photo URL surface —
  `firstPhotoUrl()`, `photoUrls()`, `responsivePhotos()` and `ReviewResource`'s `url` / `srcset`
  — asked media-library for public URLs, which it refuses for private media, so each threw
  `MediaCannotBeStreamed`. They now resolve each photo by visibility (public URL for public,
  short-lived signed URL for private) through the new `resolvePhotoUrl()` / `photoSrcset()`.
  `firstPhotoTemporaryUrl()` now defaults to `media.temporary_url_default_lifetime` instead of a
  hardcoded 5 minutes.

### Security

- Private photos are now stored on a non-public disk by default: new `photos.private_disk`
  (`REVIEWS_PHOTOS_PRIVATE_DISK`, default `local`) holds private originals and their variants
  whenever `photos.disk` is unset. They used to land on media-library's default `public` disk —
  served under `/storage` once `storage:link` runs — so a private photo was reachable without a
  signed URL. The signed stream route serves them from the private disk.
