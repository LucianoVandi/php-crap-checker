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

        usort($violations, static function (Violation $a, Violation $b): int {
            if ($a->method->crap !== $b->method->crap) {
                return $b->method->crap <=> $a->method->crap;
            }

            $complexityA = $a->method->complexity ?? 0;
            $complexityB = $b->method->complexity ?? 0;

            if ($complexityA !== $complexityB) {
                return $complexityB <=> $complexityA;
            }

            return $a->method->name <=> $b->method->name;
        });

        return $violations;
    }
}
