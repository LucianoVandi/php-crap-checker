<?php

declare(strict_types=1);

namespace Lvandi\PhpCrapChecker\Config;

final class Configuration
{
    /**
     * @param list<string> $ignorePaths
     * @param list<string> $ignoreMethods
     */
    public function __construct(
        public readonly ?string $report = null,
        public readonly ?float $threshold = null,
        public readonly ?string $format = null,
        public readonly ?int $maxViolations = null,
        public readonly array $ignorePaths = [],
        public readonly array $ignoreMethods = [],
    ) {
    }
}
