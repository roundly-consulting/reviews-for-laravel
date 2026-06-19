<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Tests;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Reviews\Concerns\HasReviews;
use RoundlyConsulting\Reviews\Concerns\MaintainsReviewAggregates;

class Catalog extends Model
{
    use HasReviews;
    use MaintainsReviewAggregates;

    public $timestamps = false;

    protected $guarded = [];

    protected $table = 'catalogs';
}
