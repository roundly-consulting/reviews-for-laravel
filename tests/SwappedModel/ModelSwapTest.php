<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\ReviewsManager;
use RoundlyConsulting\Reviews\Tests\Member;
use RoundlyConsulting\Reviews\Tests\Product;
use RoundlyConsulting\Reviews\Tests\TenantReview;
use RoundlyConsulting\Reviews\Tests\TenantVote;
use RoundlyConsulting\Reviews\Tests\Voter;

/**
 * S — the model-swap proofs for both seams, driven through the flows a host calls, from the
 * state a host actually produces: `SwappedModelTestCase` sets both keys BEFORE the providers
 * boot, which this directory is bound to (Pest binds a test case per directory, not per file).
 *
 * `tests/Feature/ConfiguredModelsTest.php` covers the same seams and is kept — it asserts real
 * domain behaviour through the host model. But it swaps in a `beforeEach`, i.e. **in the test
 * body**, after the providers have booted and the migrations have run. That shape is not a
 * detail here: the provider hangs its aggregate observer and its photo-purge hook on the
 * *configured* class at boot, and `0002_create_review_votes_table` resolves its foreign-key
 * parent through the seam at **migrate** time. A body-time swap is structurally incapable of
 * seeing a bug in either — this package's own votes-FK bug is the worked example.
 *
 * These add what ConfiguredModelsTest structurally cannot: `CountsCreations` proves each row
 * was created **as** the host class. `instanceof` passes for a row created as the packaged
 * Review and re-hydrated, which would fire none of the host's model events (permissions #31).
 */
it('honours a host review model through the writing and reading flows', function (): void {
    expect('reviews.model')->toHonourModelSwap(TenantReview::class, function (): array {
        $product = Product::create();
        $author = Member::create();

        $review = $product->addReview($author)->rating(5)->title('Great')->content('Loved it.')->create();

        // An owner response is itself a review row through the same seam — the self-referential
        // parent_id edge.
        $response = $review->respond($author, 'Thanks for the feedback.');

        return [
            $review,
            $response,
            // Reads hydrate through the seam too, not just the writes.
            ...$product->reviews()->get()->all(),
        ];
    });
});

it('honours a host vote model through the voting flow', function (): void {
    expect('reviews.vote_model')->toHonourModelSwap(TenantVote::class, function (): array {
        $review = Product::create()->addReview(Member::create())->rating(4)->content('Good.')->create();

        $vote = app(ReviewsManager::class)->vote($review, Voter::create());

        return [
            $vote,
            // The relation back off the review must resolve through the seam as well.
            ...$review->votes()->get()->all(),
        ];
    });
});

// The structural half of both seams — `Review`/`ReviewVote` are non-final, and the two keys
// really default to the packaged models — is pinned once in tests/ArchTest.php by
// `ArchPresets::swappableModelsAreNotFinal()`. It deliberately does NOT live here: that preset
// asserts the config *defaults*, which this directory has swapped away.
