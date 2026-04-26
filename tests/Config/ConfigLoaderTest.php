<?php

declare(strict_types=1);

namespace Lvandi\PhpCrapChecker\Tests\Config;

use Lvandi\PhpCrapChecker\Config\ConfigLoader;
use Lvandi\PhpCrapChecker\Exception\InvalidConfigException;
use PHPUnit\Framework\TestCase;

final class ConfigLoaderTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/crap-checker-test-' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (scandir($this->tmpDir) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                unlink($this->tmpDir . '/' . $entry);
            }
        }
        rmdir($this->tmpDir);
    }

    private function writeConfig(string $filename, string $content): void
    {
        file_put_contents($this->tmpDir . '/' . $filename, $content);
    }

    public function testNoFileReturnsEmptyConfiguration(): void
    {
        $config = (new ConfigLoader())->load($this->tmpDir);

        self::assertNull($config->report);
        self::assertNull($config->threshold);
        self::assertNull($config->format);
        self::assertNull($config->maxViolations);
        self::assertSame([], $config->ignorePaths);
        self::assertSame([], $config->ignoreMethods);
    }

    public function testPartialConfigOnlyLoadsDefinedFields(): void
    {
        $this->writeConfig('.crap-checker.yml', "threshold: 25\n");

        $config = (new ConfigLoader())->load($this->tmpDir);

        self::assertSame(25.0, $config->threshold);
        self::assertNull($config->report);
        self::assertNull($config->format);
        self::assertNull($config->maxViolations);
    }

    public function testFullConfigLoadsAllFields(): void
    {
        $this->writeConfig('.crap-checker.yml', <<<'YAML'
            report: build/crap4j.xml
            threshold: 30
            format: text
            max_violations: 50
            ignore:
              paths:
                - "src/Generated/*"
              methods:
                - "App\\Legacy\\OldImporter::handle"
            YAML);

        $config = (new ConfigLoader())->load($this->tmpDir);

        self::assertSame('build/crap4j.xml', $config->report);
        self::assertSame(30.0, $config->threshold);
        self::assertSame('text', $config->format);
        self::assertSame(50, $config->maxViolations);
        self::assertSame(['src/Generated/*'], $config->ignorePaths);
        self::assertSame(['App\\Legacy\\OldImporter::handle'], $config->ignoreMethods);
    }

    public function testEmptyYamlFileReturnsEmptyConfiguration(): void
    {
        $this->writeConfig('.crap-checker.yml', '');

        $config = (new ConfigLoader())->load($this->tmpDir);

        self::assertNull($config->threshold);
    }

    public function testEmptyMappingReturnsEmptyConfiguration(): void
    {
        $this->writeConfig('.crap-checker.yml', "{}\n");

        $config = (new ConfigLoader())->load($this->tmpDir);

        self::assertNull($config->threshold);
    }

    public function testUnknownFieldsAreIgnored(): void
    {
        $this->writeConfig('.crap-checker.yml', "threshold: 20\nsome_unknown_key: true\n");

        $config = (new ConfigLoader())->load($this->tmpDir);

        self::assertSame(20.0, $config->threshold);
    }

    public function testMalformedYamlThrowsInvalidConfigException(): void
    {
        $this->writeConfig('.crap-checker.yml', "threshold: [\ninvalid yaml");

        $this->expectException(InvalidConfigException::class);
        (new ConfigLoader())->load($this->tmpDir);
    }

    public function testThresholdWithWrongTypeThrowsInvalidConfigException(): void
    {
        $this->writeConfig('.crap-checker.yml', "threshold: \"not-a-number\"\n");

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessageMatches('/threshold/');
        (new ConfigLoader())->load($this->tmpDir);
    }

    public function testFormatWithWrongTypeThrowsInvalidConfigException(): void
    {
        $this->writeConfig('.crap-checker.yml', "format: 123\n");

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessageMatches('/format/');
        (new ConfigLoader())->load($this->tmpDir);
    }

    public function testMaxViolationsWithNegativeValueThrowsInvalidConfigException(): void
    {
        $this->writeConfig('.crap-checker.yml', "max_violations: -1\n");

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessageMatches('/max_violations/');
        (new ConfigLoader())->load($this->tmpDir);
    }

    public function testIgnorePathsWithNonListThrowsInvalidConfigException(): void
    {
        $this->writeConfig('.crap-checker.yml', "ignore:\n  paths: \"not-a-list\"\n");

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessageMatches('/ignore\.paths/');
        (new ConfigLoader())->load($this->tmpDir);
    }

    public function testIgnoreMethodsWithNonStringItemThrowsInvalidConfigException(): void
    {
        $this->writeConfig('.crap-checker.yml', "ignore:\n  methods:\n    - 123\n");

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessageMatches('/ignore\.methods/');
        (new ConfigLoader())->load($this->tmpDir);
    }

    public function testThresholdAsFloatIsParsedCorrectly(): void
    {
        $this->writeConfig('.crap-checker.yml', "threshold: 15.5\n");

        $config = (new ConfigLoader())->load($this->tmpDir);

        self::assertSame(15.5, $config->threshold);
    }

    public function testIgnoreSectionWithOnlyPathsLoadsCorrectly(): void
    {
        $this->writeConfig('.crap-checker.yml', "ignore:\n  paths:\n    - \"src/Gen/*\"\n");

        $config = (new ConfigLoader())->load($this->tmpDir);

        self::assertSame(['src/Gen/*'], $config->ignorePaths);
        self::assertSame([], $config->ignoreMethods);
    }
}
