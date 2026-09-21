<?php

namespace App\Enerjisa\Services\Reactive;

use Carbon\CarbonImmutable;

final readonly class Reading
{
    public function __construct(
        public CarbonImmutable $time,
        public string $meter,
        public ?string $active,
        public ?string $inductive,
        public ?string $capacitive,
        public int $queryId,
        public bool $conflict = false,
    ) {}
}
