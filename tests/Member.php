<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Tests;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Reviews\Concerns\CanReview;

class Member extends Model
{
    use CanReview;

    public $timestamps = false;

    protected $guarded = [];

    protected $table = 'entities';
}
