<?php

declare(strict_types=1);

namespace Lvandi\PhpCrapChecker\Analyzer;

use Lvandi\PhpCrapChecker\Result\Violation;
use Lvandi\PhpCrapChecker\ValueObject\MethodMetric;

final class CrapAnalyzer
{
    /**
     * @param list<MethodMetric> $methods
     * @return list<Violation>
     */
    public function findViolations(array $methods, float $threshold): array
    {
        $violations = [];

        foreach ($methods as $method) {
            if ($method->crap > $threshold) {
                $violations[] = new Violation($method, $threshold);
            }
        }

        usort($violations, static function (Violation $left, Violation $right): int {
            if ($left->method->crap !== $right->method->crap) {
                return $right->method->crap <=> $left->method->crap;
            }

            $complexityLeft = $left->method->complexity ?? 0;
            $complexityRight = $right->method->complexity ?? 0;

            if ($complexityLeft !== $complexityRight) {
                return $complexityRight <=> $complexityLeft;
            }

            return $left->method->name <=> $right->method->name;
        });

        return $violations;
    }
}
