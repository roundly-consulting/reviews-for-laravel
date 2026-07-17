<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Tests;

use RoundlyConsulting\Reviews\Models\ReviewVote;
use RoundlyConsulting\Testing\Fixtures\Concerns\CountsCreations;

/**
 * A host's own vote model — the swap `reviews.vote_model` invites.
 *
 * It deliberately stays on the packaged `review_votes` table, which is the ordinary shape
 * of this swap (subclass to add behaviour or relations). The other shape — a host vote
 * model on its OWN table — is not exercised because the package cannot support it today:
 * `0002_create_review_votes_table` calls `Schema::create('review_votes')` with a **literal**,
 * even though `Schema::create`'s sibling on the same line resolves its FK parent through
 * `ReviewModel::table()`. Support\ReviewVoteModel has no `table()` mirror at all. That gap
 * is recorded, not fixed here: closing it is a schema change.
 *
 * `CountsCreations` is required by `toHonourModelSwap` — asserting the concrete class of a
 * returned object cannot tell a row really created as this class from one created as the
 * packaged ReviewVote and re-hydrated.
 */
class TenantVote extends ReviewVote
{
    use CountsCreations;

    protected $table = 'review_votes';
}
