<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Tests;

use RoundlyConsulting\Reviews\Models\Review;

class CustomReview extends Review
{
    protected $table = 'reviews';
}
