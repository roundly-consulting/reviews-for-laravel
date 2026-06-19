<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Tests;

use Illuminate\Database\Eloquent\Model;

class Entity extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}
