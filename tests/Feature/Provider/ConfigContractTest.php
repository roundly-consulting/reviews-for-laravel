<?php

declare(strict_types=1);

/**
 * C — the config contract, pinned in both directions.
 *
 * This file replaces ~130 lines of hand-rolled reinvention: the suite carried its own
 * `readConfigKeys()` tokenizer, its own `sourceFiles()` walker and its own config
 * flattener. The ideas were right — it even tokenized rather than regexed, which is the
 * trap media #27 fell into — and that is exactly why it should be the shared
 * implementation rather than this package's copy of it.
 *
 * What the local version could not do, and the preset does:
 *  - it counted only `'reviews.…'` string literals, so an injected `Repository::get()` or a
 *    `Config::get()` read was invisible to it;
 *  - it silently ignored interpolated keys instead of flagging them as uncheckable — an
 *    unresolvable key makes the read-set unsound, so reverse findings computed from it
 *    would be invented dead keys;
 *  - `allowUnread`/`allowUnshipped` are rot-proof here — a stale entry that silences
 *    nothing is itself a failure. A hand-rolled skip list rots quietly.
 *
 * The bugs both directions exist for:
 *  - forward — shops #18: the whole store-credit feature read `shops.payments.*` while the
 *    file shipped `payment.*`; 330 tests stayed green because the suite set the same wrong
 *    key.
 *  - reverse — media #27's `max_file_size` cap that never applied. Reviews ships its own
 *    `photos.max_file_size`, so that is not a distant analogy: a host that sets it believes
 *    uploads are capped.
 */
it('ships exactly the config keys it reads', function (): void {
    expect(__DIR__.'/../../../config/reviews.php')->toSatisfyConfigContract(
        [__DIR__.'/../../../src', __DIR__.'/../../../database'],
        [
            // `reviews.model` and `reviews.vote_model` are read through the toolkit's
            // `ModelResolver::for(…)` seam (via Support\ReviewModel / Support\ReviewVoteModel),
            // not as a `config(` token, so the prefix is what makes those real reads visible
            // to the scraper. `database/` is scanned as a srcDir because
            // 0002_create_review_votes_table resolves its FK parent through the seam.
            'extraReadPrefixes' => ['reviews.'],

            // Deliberately NO `excludeFromReverse` for the provider. The testing README's
            // example excludes the service provider on the grounds that "a render is not a
            // read" — but the toolkit's PackageServiceProvider both `contributesToAbout()`
            // and does real config reads in one file, so excluding it would discard the only
            // reader of several bound keys and weaken the reverse direction for nothing.
        ],
    );
});
