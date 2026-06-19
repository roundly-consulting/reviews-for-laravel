<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Tests;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Reviews\Concerns\CanVoteOnReviews;

class Voter extends Model
{
    use CanVoteOnReviews;

    public $timestamps = false;

    protected $guarded = [];

    protected $table = 'entities';
}
