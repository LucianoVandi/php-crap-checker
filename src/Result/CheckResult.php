<?php

declare(strict_types=1);

namespace Lvandi\PhpCrapChecker\Result;

final readonly class CheckResult
{
    /**
     * @param list<Violation> $violations
     */
    public function __construct(
        public array $violations,
        public int $totalMethods,
    ) {
    }

    public function hasViolations(): bool
    {
        return $this->violations !== [];
    }
}
