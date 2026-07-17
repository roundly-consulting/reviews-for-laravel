<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\Exceptions\ReviewException;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Models\ReviewVote;
use RoundlyConsulting\Reviews\Reviews;
use RoundlyConsulting\Reviews\Support\PendingReview;
use RoundlyConsulting\Testing\Arch\ArchPresets;

/**
 * Reviews shipped a one-line arch file (`dd`/`dump`/`ray`), so every preset here is a new
 * guard rather than a replacement.
 */
ArchPresets::strictTypes('RoundlyConsulting\Reviews');

/**
 * The deliberate extension points are exempt: `Review` and `ReviewVote` are what
 * `reviews.model` / `reviews.vote_model` invite a host to subclass (pinned by the preset
 * below instead), and ReviewException is the base every reviews error extends so a host can
 * catch them uniformly.
 *
 * Note the `$ignoring` PARAMETER rather than Pest's fluent `->ignoring()`. Only the
 * parameter is rot-checked (by `exemptionsExist` below): the fluent form accepts any string
 * and never verifies it, so a typo or an exemption that outlived its code is a silent no-op.
 * That is not hypothetical here — this list was first written with a `ReviewsException`
 * class that does not exist, and the fluent form swallowed it without a word.
 */
ArchPresets::finalByDefault('RoundlyConsulting\Reviews', [
    Review::class,
    ReviewVote::class,
    ReviewException::class,
    // The package extends both itself, for Reviews::fake(): Testing\ReviewsFake extends
    // Reviews and Testing\RecordingPendingReview extends PendingReview. Real, in-tree
    // extension points rather than oversights - and both are proven so by exemptionsExist,
    // which the $ignoring parameter registers automatically.
    Reviews::class,
    PendingReview::class,
]);

/**
 * The counter-weight, and the fleet's 7×-shipped fatal: `final` on a config-swappable model
 * is a PHP fatal the moment a host uses the seam the config documents. Nothing stopped that
 * arriving here — the package had no finality rule at all.
 *
 * `reviews.moderator` is deliberately absent. It is a swappable *strategy*
 * (`NullModerator`, behind a Moderator contract), not an Eloquent model behind a
 * `*_model`-shaped key, so this preset — which resolves the key and asserts on a model —
 * has nothing to say about it. Counting it as a third `S` would be counting the config
 * shape rather than the seam.
 */
ArchPresets::swappableModelsAreNotFinal([
    Review::class => 'reviews.model',
    ReviewVote::class => 'reviews.vote_model',
]);

/**
 * Reviews does no cryptography and has no reason to start. A standing guard.
 */
ArchPresets::noLocalCryptoPrimitives('RoundlyConsulting\Reviews');

/**
 * Both model keys are read through Support\ReviewModel / Support\ReviewVoteModel (which
 * delegate to the toolkit's ModelResolver). Adopted rather than rejected as jwt rejected
 * it: reviews has exactly the shape the preset targets — real Eloquent models behind
 * `*model` keys, resolved through a Support seam. The keys are declared rather than
 * inferred so the preset polices the two this package means and stays silent about
 * `reviews.moderator`.
 */
ArchPresets::modelsResolveThroughSeam(__DIR__.'/../src', 'Support', [
    'reviews.model',
    'reviews.vote_model',
]);

/**
 * The Dependency Policy as a test. No `alsoAllow`: reviews' `require` ships only
 * php/illuminate/roundly, and the workflow installs test tooling with `--dev`, so nothing
 * legitimately lands in `require` that this must forgive. If it goes red the graph is wrong
 * — never widen the allow-list to quiet it (bug #6 is a true positive).
 */
ArchPresets::runtimeRequireIsWhitelisted(__DIR__.'/../composer.json');

/**
 * Replaces the package's entire previous arch file, which named three functions; the preset
 * covers the full leftover set.
 */
ArchPresets::noDebuggingLeftovers();
