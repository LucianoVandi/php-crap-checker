<?php

declare(strict_types=1);

namespace Lvandi\PhpCrapChecker\Command;

final class ResolvedOptions
{
    /**
     * @param list<string> $ignorePaths
     * @param list<string> $ignoreMethods
     */
    public function __construct(
        public readonly string $reportPath,
        public readonly float $threshold,
        public readonly string $format,
        public readonly ?int $maxViolations,
        public readonly ?int $maxAgeSeconds,
        public readonly array $ignorePaths = [],
        public readonly array $ignoreMethods = [],
    ) {
    }
}
