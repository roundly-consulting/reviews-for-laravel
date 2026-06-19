<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Tests;

use RoundlyConsulting\Reviews\Testing\InteractsWithReviews;

/**
 * Exercises the InteractsWithReviews trait so static analysis covers it. Host
 * apps mix the trait into their own test cases instead.
 */
class ReviewerHarness
{
    use InteractsWithReviews;
}
