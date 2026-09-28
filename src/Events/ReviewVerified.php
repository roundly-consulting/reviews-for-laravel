<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Events;

use Illuminate\Foundation\Events\Dispatchable;
use RoundlyConsulting\Reviews\Models\Review;

/**
 * A review's verified flag changed — `$verified` is the new state (`true` from
 * `Reviews::verify()`, `false` from `Reviews::unverify()`).
 */
final class ReviewVerified
{
    use Dispatchable;

    public function __construct(
        public Review $review,
        public bool $verified,
    ) {}
}
