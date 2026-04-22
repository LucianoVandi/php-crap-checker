<?php

declare(strict_types=1);

namespace Lvandi\PhpCrapChecker\ValueObject;

final readonly class MethodMetric
{
    public function __construct(
        public string $name,
        public float $crap,
        public ?string $className = null,
        public ?string $file = null,
        public ?int $line = null,
        public ?int $complexity = null,
        public ?float $coverage = null,
    ) {
    }
}
