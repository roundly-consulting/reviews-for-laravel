<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A review's subject as ReviewResource renders it: the key and the model's class basename
 * (`Product`), never its class name. Wraps the related model. Swap it through
 * `reviews.resources.reviewable` for your own JsonResource to add a title, a URL, …
 *
 * @property Model $resource
 */
final class ReviewableResource extends JsonResource
{
    /**
     * @return array{id: mixed, type: string}
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->resource->getKey(), 'type' => class_basename($this->resource)];
    }
}
