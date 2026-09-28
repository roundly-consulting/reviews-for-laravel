<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\Facades\Reviews;

/**
 * The Actions → Manager → Facade contract, pinned: the facade documents every public
 * ReviewsManager method, `Reviews::fake()` swaps in a ReviewsFake that subtypes the manager (so
 * injected managers get it too), and every non-@internal action under src/Actions is reachable
 * from the facade — ValidatesRating and RecountReviewVotes are @internal building blocks.
 */
it('pins the reviews facade contract', function (): void {
    expect(Reviews::class)
        ->toDocumentItsRoot()
        ->toBeFakeable()
        ->toReachEveryAction(__DIR__.'/../../src/Actions');
});
