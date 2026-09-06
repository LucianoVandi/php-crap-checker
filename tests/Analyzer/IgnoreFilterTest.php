<?php

declare(strict_types=1);

namespace Lvandi\PhpCrapChecker\Tests\Analyzer;

use Lvandi\PhpCrapChecker\Analyzer\IgnoreFilter;
use Lvandi\PhpCrapChecker\ValueObject\MethodMetric;
use PHPUnit\Framework\TestCase;

final class IgnoreFilterTest extends TestCase
{
    private IgnoreFilter $filter;

    protected function setUp(): void
    {
        $this->filter = new IgnoreFilter();
    }

    public function testNoIgnoreRulesReturnsMethodsUnchanged(): void
    {
        $methods = [new MethodMetric('foo', 50.0)];

        $result = $this->filter->filter($methods, [], []);

        self::assertSame($methods, $result);
    }

    public function testMethodMatchingIgnoreMethodsIsExcluded(): void
    {
        $keep = new MethodMetric('handle', 50.0, className: 'App\\Service\\Importer');
        $ignore = new MethodMetric('handle', 50.0, className: 'App\\Legacy\\OldImporter');

        $result = $this->filter->filter([$keep, $ignore], [], ['App\\Legacy\\OldImporter::handle']);

        self::assertSame([$keep], $result);
    }

    public function testMethodWithoutClassNameMatchedByBareName(): void
    {
        $method = new MethodMetric('globalFunction', 50.0);

        $result = $this->filter->filter([$method], [], ['globalFunction']);

        self::assertSame([], $result);
    }

    public function testMethodMatchingIgnorePathGlobIsExcluded(): void
    {
        $keep = new MethodMetric('foo', 50.0, file: 'src/Service/Foo.php');
        $ignore = new MethodMetric('bar', 50.0, file: 'src/Generated/Bar.php');

        $result = $this->filter->filter([$keep, $ignore], ['src/Generated/*'], []);

        self::assertSame([$keep], $result);
    }

    public function testGlobWildcardDoesNotMatchAcrossDirectorySeparators(): void
    {
        $method = new MethodMetric('foo', 50.0, file: 'src/Generated/Sub/Foo.php');

        $result = $this->filter->filter([$method], ['src/Generated/*'], []);

        self::assertSame([$method], $result);
    }

    public function testMethodWithoutFileIsNeverMatchedByPathPattern(): void
    {
        $method = new MethodMetric('foo', 50.0);

        $result = $this->filter->filter([$method], ['src/Generated/*'], []);

        self::assertSame([$method], $result);
    }

    public function testResultIsReindexedAfterFiltering(): void
    {
        $ignore = new MethodMetric('bar', 50.0, file: 'src/Generated/Bar.php');
        $keep = new MethodMetric('foo', 50.0, file: 'src/Service/Foo.php');

        $result = $this->filter->filter([$ignore, $keep], ['src/Generated/*'], []);

        self::assertSame([0 => $keep], $result);
    }

    public function testMatchingEitherRuleIsSufficientToExclude(): void
    {
        $byPath = new MethodMetric('a', 50.0, file: 'src/Generated/A.php');
        $byMethod = new MethodMetric('handle', 50.0, className: 'App\\Legacy\\OldImporter');
        $kept = new MethodMetric('b', 50.0, className: 'App\\Service\\B', file: 'src/Service/B.php');

        $result = $this->filter->filter(
            [$byPath, $byMethod, $kept],
            ['src/Generated/*'],
            ['App\\Legacy\\OldImporter::handle'],
        );

        self::assertSame([$kept], $result);
    }
}
