<?php

declare(strict_types=1);

namespace Lvandi\PhpCrapChecker\Tests\Analyzer;

use Lvandi\PhpCrapChecker\Analyzer\CrapAnalyzer;
use Lvandi\PhpCrapChecker\Result\Violation;
use Lvandi\PhpCrapChecker\ValueObject\MethodMetric;
use PHPUnit\Framework\TestCase;

final class CrapAnalyzerTest extends TestCase
{
    private CrapAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new CrapAnalyzer();
    }

    public function testExactlyAtThresholdIsNotAViolation(): void
    {
        $methods = [new MethodMetric('foo', 30.0)];

        $violations = $this->analyzer->findViolations($methods, 30.0);

        self::assertSame([], $violations);
    }

    public function testJustAboveThresholdIsAViolation(): void
    {
        $method = new MethodMetric('foo', 30.01);

        $violations = $this->analyzer->findViolations([$method], 30.0);

        self::assertCount(1, $violations);
        self::assertInstanceOf(Violation::class, $violations[0]);
        self::assertSame($method, $violations[0]->method);
        self::assertSame(30.0, $violations[0]->threshold);
    }

    public function testEmptyMethodListReturnsNoViolations(): void
    {
        $violations = $this->analyzer->findViolations([], 30.0);

        self::assertSame([], $violations);
    }

    public function testMethodsBelowThresholdAreIgnored(): void
    {
        $methods = [
            new MethodMetric('a', 10.0),
            new MethodMetric('b', 29.99),
        ];

        $violations = $this->analyzer->findViolations($methods, 30.0);

        self::assertSame([], $violations);
    }

    public function testViolationsAreSortedByCrapDescThenComplexityDescThenNameAsc(): void
    {
        $methods = [
            new MethodMetric('zebra', 50.0, complexity: 5),
            new MethodMetric('alpha', 50.0, complexity: 5),
            new MethodMetric('middle', 50.0, complexity: 8),
            new MethodMetric('high', 72.0, complexity: 10),
            new MethodMetric('low', 31.0, complexity: 3),
        ];

        $violations = $this->analyzer->findViolations($methods, 30.0);

        self::assertCount(5, $violations);

        $names = array_map(fn (Violation $v): string => $v->method->name, $violations);
        self::assertSame(['high', 'middle', 'alpha', 'zebra', 'low'], $names);
    }

    public function testSortingWithNullComplexityTreatedAsZero(): void
    {
        $methods = [
            new MethodMetric('withComplexity', 50.0, complexity: 3),
            new MethodMetric('noComplexity', 50.0, complexity: null),
        ];

        $violations = $this->analyzer->findViolations($methods, 30.0);

        $names = array_map(fn (Violation $v): string => $v->method->name, $violations);
        self::assertSame(['withComplexity', 'noComplexity'], $names);
    }

    public function testNullComplexityOnLeftSideOrderedAfterPositiveComplexity(): void
    {
        // Ensures ?? 0 on $complexityLeft is correct: null < positive → comes after
        $methods = [
            new MethodMetric('nullFirst', 50.0, complexity: null),
            new MethodMetric('hasComplexity', 50.0, complexity: 1),
        ];

        $violations = $this->analyzer->findViolations($methods, 30.0);

        $names = array_map(fn (Violation $v): string => $v->method->name, $violations);
        self::assertSame(['hasComplexity', 'nullFirst'], $names);
    }

    public function testBothNullComplexityFallsThroughToNameOrdering(): void
    {
        // Ensures ?? 0 on both sides: equal complexity → sort by name asc
        $methods = [
            new MethodMetric('zebra', 50.0, complexity: null),
            new MethodMetric('alpha', 50.0, complexity: null),
        ];

        $violations = $this->analyzer->findViolations($methods, 30.0);

        $names = array_map(fn (Violation $v): string => $v->method->name, $violations);
        self::assertSame(['alpha', 'zebra'], $names);
    }

    public function testNullComplexityEqualsZeroComplexityFallsThroughToName(): void
    {
        // null ?? 0 and explicit 0 are equal → sort by name
        $methods = [
            new MethodMetric('zebra', 50.0, complexity: 0),
            new MethodMetric('alpha', 50.0, complexity: null),
        ];

        $violations = $this->analyzer->findViolations($methods, 30.0);

        $names = array_map(fn (Violation $v): string => $v->method->name, $violations);
        self::assertSame(['alpha', 'zebra'], $names);
    }

    public function testViolationCarriesCorrectThreshold(): void
    {
        $methods = [new MethodMetric('foo', 100.0)];

        $violations = $this->analyzer->findViolations($methods, 42.5);

        self::assertSame(42.5, $violations[0]->threshold);
    }

    public function testCrapOrderTakesPriorityOverComplexityOrder(): void
    {
        // Kills ReturnRemoval on the crap-comparison branch:
        // without the return, sorting falls through to complexity and reverses the order.
        $methods = [
            new MethodMetric('highCrapLowComplexity', 72.0, complexity: 2),
            new MethodMetric('lowCrapHighComplexity', 40.0, complexity: 10),
        ];

        $violations = $this->analyzer->findViolations($methods, 30.0);

        $names = array_map(fn (Violation $v): string => $v->method->name, $violations);
        self::assertSame(['highCrapLowComplexity', 'lowCrapHighComplexity'], $names);
    }

    public function testNullComplexityAsLeftEqualsZeroComplexityAsRight(): void
    {
        // Kills DecrementInteger (?? 0 → ?? -1) on $complexityLeft.
        // With ?? -1: complexityLeft=-1 != 0 → complexity-desc puts right first → ['zebra', 'alpha'].
        // With ?? 0: equal → name-asc → ['alpha', 'zebra'].
        $methods = [
            new MethodMetric('alpha', 50.0, complexity: null),
            new MethodMetric('zebra', 50.0, complexity: 0),
        ];

        $violations = $this->analyzer->findViolations($methods, 30.0);

        $names = array_map(fn (Violation $v): string => $v->method->name, $violations);
        self::assertSame(['alpha', 'zebra'], $names);
    }

    public function testZeroComplexityAsLeftEqualsNullComplexityAsRight(): void
    {
        // Kills IncrementInteger (?? 0 → ?? 1) on $complexityRight.
        // With ?? 1: complexityRight=1 != 0 → complexity-desc puts right first → ['zebra', 'alpha'].
        // With ?? 0: equal → name-asc → ['alpha', 'zebra'].
        $methods = [
            new MethodMetric('alpha', 50.0, complexity: 0),
            new MethodMetric('zebra', 50.0, complexity: null),
        ];

        $violations = $this->analyzer->findViolations($methods, 30.0);

        $names = array_map(fn (Violation $v): string => $v->method->name, $violations);
        self::assertSame(['alpha', 'zebra'], $names);
    }
}
