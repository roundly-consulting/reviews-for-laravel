<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\Models\Review;

return [

    /*
    |--------------------------------------------------------------------------
    | Review Model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model used to persist reviews. Override this with your own
    | model (extending the package model) when you need custom behaviour.
    |
    */

    'model' => Review::class,

];
