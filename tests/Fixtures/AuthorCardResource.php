<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A host's own author resource, swapped in through `reviews.resources.author`.
 *
 * @property Model $resource
 */
final class AuthorCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'name' => 'Member #'.$this->resource->getKey(),
            'avatar' => null,
        ];
    }
}
