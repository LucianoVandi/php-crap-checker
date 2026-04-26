<?php

declare(strict_types=1);

namespace Lvandi\PhpCrapChecker\Command;

final class ResolvedOptions
{
    public function __construct(
        public readonly string $reportPath,
        public readonly float $threshold,
        public readonly string $format,
        public readonly ?int $maxViolations,
        public readonly ?int $maxAgeSeconds,
    ) {
    }
}
