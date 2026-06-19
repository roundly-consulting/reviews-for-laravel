<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\DataTransferObjects;

final readonly class RatingSummary
{
    /**
     * @param  array<int, int>  $distribution  Map of rating value => count.
     */
    public function __construct(
        public ?float $average,
        public int $count,
        public array $distribution,
    ) {}

    /**
     * @return array{average: float|null, count: int, distribution: array<int, int>}
     */
    public function toArray(): array
    {
        return [
            'average' => $this->average,
            'count' => $this->count,
            'distribution' => $this->distribution,
        ];
    }
}
