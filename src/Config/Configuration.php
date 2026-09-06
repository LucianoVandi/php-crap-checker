<?php

declare(strict_types=1);

namespace Lvandi\PhpCrapChecker\Config;

final readonly class Configuration
{
    /**
     * @param list<string> $ignorePaths
     * @param list<string> $ignoreMethods
     */
    public function __construct(
        public ?string $report = null,
        public ?float $threshold = null,
        public ?string $format = null,
        public ?int $maxViolations = null,
        public array $ignorePaths = [],
        public array $ignoreMethods = [],
    ) {
    }
}
