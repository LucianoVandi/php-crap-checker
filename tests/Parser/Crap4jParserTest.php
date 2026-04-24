<?php

declare(strict_types=1);

namespace Lvandi\PhpCrapChecker\Tests\Parser;

use Lvandi\PhpCrapChecker\Exception\InvalidReportException;
use Lvandi\PhpCrapChecker\Exception\ReportNotFoundException;
use Lvandi\PhpCrapChecker\Parser\Crap4jParser;
use Lvandi\PhpCrapChecker\ValueObject\MethodMetric;
use PHPUnit\Framework\TestCase;

final class Crap4jParserTest extends TestCase
{
    private Crap4jParser $parser;
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->parser = new Crap4jParser();
        $this->fixturesDir = __DIR__ . '/../Fixtures';
    }

    public function testParsesValidFixtureAndReturnsMethodMetrics(): void
    {
        $methods = $this->parser->parse($this->fixturesDir . '/crap4j-valid.xml');

        self::assertCount(3, $methods);
    }

    public function testParsedMethodHasCorrectValues(): void
    {
        $methods = $this->parser->parse($this->fixturesDir . '/crap4j-valid.xml');

        $first = $methods[0];
        self::assertSame('findViolations', $first->name);
        self::assertSame(3.0, $first->crap);
        self::assertSame(\Lvandi\PhpCrapChecker\Analyzer\CrapAnalyzer::class, $first->className);
        self::assertNull($first->file);
        self::assertNull($first->line);
        self::assertSame(3, $first->complexity);
        self::assertEqualsWithDelta(100.0, $first->coverage, 0.01);
    }

    public function testParsesWithViolationsFixture(): void
    {
        $methods = $this->parser->parse($this->fixturesDir . '/crap4j-with-violations.xml');

        self::assertCount(6, $methods);

        $crapValues = array_map(fn (MethodMetric $m): float => $m->crap, $methods);
        self::assertContains(46.23, $crapValues);
        self::assertContains(72.0, $crapValues);
    }

    public function testEmptyReportReturnsEmptyArray(): void
    {
        $methods = $this->parser->parse($this->fixturesDir . '/crap4j-empty.xml');

        self::assertSame([], $methods);
    }

    public function testMissingFileThrowsReportNotFoundException(): void
    {
        $this->expectException(ReportNotFoundException::class);
        $this->expectExceptionMessage('Report not found: /non/existent/file.xml');

        $this->parser->parse('/non/existent/file.xml');
    }

    public function testUnreadableFileThrowsReportNotFoundException(): void
    {
        if (posix_getuid() === 0) {
            $this->markTestSkipped('Cannot test unreadable files as root');
        }

        $tmpFile = sys_get_temp_dir() . '/unreadable-crap4j-' . uniqid() . '.xml';
        file_put_contents($tmpFile, '<crap_result/>');
        chmod($tmpFile, 0o000);

        try {
            $this->expectException(ReportNotFoundException::class);
            $this->parser->parse($tmpFile);
        } finally {
            chmod($tmpFile, 0o644);
            @unlink($tmpFile);
        }
    }

    public function testMalformedXmlThrowsInvalidReportException(): void
    {
        $malformedFile = sys_get_temp_dir() . '/malformed-crap4j-' . uniqid() . '.xml';
        file_put_contents($malformedFile, '<?xml version="1.0"?><unclosed>');

        try {
            $this->expectException(InvalidReportException::class);
            $this->parser->parse($malformedFile);
        } finally {
            @unlink($malformedFile);
        }
    }

    public function testLibxmlErrorStateIsRestoredAfterMalformedXml(): void
    {
        $malformedFile = sys_get_temp_dir() . '/malformed-crap4j-' . uniqid() . '.xml';
        file_put_contents($malformedFile, '<?xml version="1.0"?><unclosed>');

        $stateBefore = libxml_use_internal_errors(false);

        try {
            $this->parser->parse($malformedFile);
        } catch (\Throwable) {
            // expected
        } finally {
            @unlink($malformedFile);
        }

        self::assertFalse(libxml_use_internal_errors($stateBefore));
    }

    public function testLibxmlErrorStateIsRestoredAfterValidParse(): void
    {
        $stateBefore = libxml_use_internal_errors(false);

        $this->parser->parse($this->fixturesDir . '/crap4j-valid.xml');

        self::assertFalse(libxml_use_internal_errors($stateBefore));
    }

    public function testLibxmlErrorsAreNotLeakedBetweenCalls(): void
    {
        $malformedFile = sys_get_temp_dir() . '/malformed-crap4j-' . uniqid() . '.xml';
        file_put_contents($malformedFile, '<?xml version="1.0"?><unclosed>');

        try {
            $this->parser->parse($malformedFile);
        } catch (\Throwable) {
        } finally {
            @unlink($malformedFile);
        }

        $methods = $this->parser->parse($this->fixturesDir . '/crap4j-valid.xml');
        self::assertCount(3, $methods);
    }

    public function testMethodWithoutCrapValueIsSkipped(): void
    {
        $xmlWithMissingCrap = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <crap_result>
                <methods>
                    <method>
                        <className>App\Foo</className>
                        <methodName>bar</methodName>
                        <complexity>2</complexity>
                    </method>
                    <method>
                        <className>App\Foo</className>
                        <methodName>baz</methodName>
                        <crap>5.0</crap>
                        <complexity>2</complexity>
                    </method>
                </methods>
            </crap_result>
            XML;

        $tmpFile = sys_get_temp_dir() . '/crap4j-nocrap-' . uniqid() . '.xml';
        file_put_contents($tmpFile, $xmlWithMissingCrap);

        try {
            $methods = $this->parser->parse($tmpFile);
            self::assertCount(1, $methods);
            self::assertSame('baz', $methods[0]->name);
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testOptionalFieldsAreNullWhenMissing(): void
    {
        $xmlMinimal = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <crap_result>
                <methods>
                    <method>
                        <className>App\Minimal</className>
                        <methodName>run</methodName>
                        <crap>10.0</crap>
                    </method>
                </methods>
            </crap_result>
            XML;

        $tmpFile = sys_get_temp_dir() . '/crap4j-minimal-' . uniqid() . '.xml';
        file_put_contents($tmpFile, $xmlMinimal);

        try {
            $methods = $this->parser->parse($tmpFile);
            self::assertCount(1, $methods);
            self::assertSame('run', $methods[0]->name);
            self::assertSame(10.0, $methods[0]->crap);
            self::assertNull($methods[0]->file);
            self::assertNull($methods[0]->line);
            self::assertNull($methods[0]->complexity);
            self::assertNull($methods[0]->coverage);
        } finally {
            @unlink($tmpFile);
        }
    }
}
