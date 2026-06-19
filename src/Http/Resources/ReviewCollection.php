<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

final class ReviewCollection extends ResourceCollection
{
    public $collects = ReviewResource::class;
}
