<?php

declare(strict_types=1);

namespace Lvandi\PhpCrapChecker\Command;

final readonly class ResolvedOptions
{
    /**
     * @param list<string> $ignorePaths
     * @param list<string> $ignoreMethods
     */
    public function __construct(
        public string $reportPath,
        public float $threshold,
        public string $format,
        public ?int $maxViolations,
        public ?int $maxAgeSeconds,
        public array $ignorePaths = [],
        public array $ignoreMethods = [],
    ) {
    }
}
