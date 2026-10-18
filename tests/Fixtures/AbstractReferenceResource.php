<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Tests\Fixtures;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A JsonResource subclass that can never be built — `reviews.resources.*` must refuse it.
 */
abstract class AbstractReferenceResource extends JsonResource {}
