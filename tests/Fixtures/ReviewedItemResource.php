<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A host's own subject resource, swapped in through `reviews.resources.reviewable`.
 *
 * @property Model $resource
 */
final class ReviewedItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'title' => 'Item #'.$this->resource->getKey(),
        ];
    }
}
