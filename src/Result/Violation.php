<?php

declare(strict_types=1);

namespace Lvandi\PhpCrapChecker\Result;

use Lvandi\PhpCrapChecker\ValueObject\MethodMetric;

final readonly class Violation
{
    public function __construct(
        public MethodMetric $method,
        public float $threshold,
    ) {
    }
}
