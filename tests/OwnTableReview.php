<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Tests;

use RoundlyConsulting\Reviews\Models\Review;

/**
 * A host review model on its own table that the host has NOT created itself — the package's own
 * reviews migration is what should create it.
 */
class OwnTableReview extends Review
{
    protected $table = 'own_reviews';
}
