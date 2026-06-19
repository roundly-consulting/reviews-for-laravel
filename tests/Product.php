<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Tests;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Reviews\Concerns\HasReviews;

class Product extends Model
{
    use HasReviews;

    public $timestamps = false;

    protected $guarded = [];

    protected $table = 'entities';
}
