<?php

declare(strict_types=1);

namespace Lvandi\PhpCrapChecker\Tests\Command;

use Lvandi\PhpCrapChecker\Command\CheckCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class CheckCommandConfigTest extends TestCase
{
    private string $tmpDir;
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/crap-checker-cmd-' . uniqid();
        mkdir($this->tmpDir, 0755, true);
        $this->fixturesDir = __DIR__ . '/../Fixtures';
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

    private function makeTester(?string $cwd = null): CommandTester
    {
        $application = new Application();
        $application->add(new CheckCommand(cwd: $cwd ?? $this->tmpDir));

        return new CommandTester($application->find('check'));
    }

    private function writeConfig(string $content): void
    {
        file_put_contents($this->tmpDir . '/.crap-checker.yml', $content);
    }

    public function testNoConfigFileWorksAsBeforeWithDefaults(): void
    {
        $tester = $this->makeTester();
        $tester->execute(['report' => $this->fixturesDir . '/crap4j-valid.xml']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Max allowed: 30', $tester->getDisplay());
    }

    public function testConfigThresholdIsUsedWhenNoCliOption(): void
    {
        $this->writeConfig("threshold: 15\n");

        $tester = $this->makeTester();
        $tester->execute(['report' => $this->fixturesDir . '/crap4j-valid.xml']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Max allowed: 15', $tester->getDisplay());
    }

    public function testCliThresholdOverridesConfig(): void
    {
        $this->writeConfig("threshold: 999\n");

        $tester = $this->makeTester();
        $tester->execute([
            'report' => $this->fixturesDir . '/crap4j-valid.xml',
            '--threshold' => '30',
        ]);

        self::assertStringContainsString('Max allowed: 30', $tester->getDisplay());
    }

    public function testConfigReportIsUsedWhenNoCliArgument(): void
    {
        $this->writeConfig(sprintf("report: %s\n", $this->fixturesDir . '/crap4j-valid.xml'));

        $tester = $this->makeTester();
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
    }

    public function testCliReportOverridesConfig(): void
    {
        $this->writeConfig(sprintf("report: %s\n", $this->fixturesDir . '/crap4j-with-violations.xml'));

        $tester = $this->makeTester();
        $tester->execute(['report' => $this->fixturesDir . '/crap4j-valid.xml']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('CRAP threshold OK', $tester->getDisplay());
    }

    public function testConfigFormatIsUsedWhenNoCliOption(): void
    {
        $this->writeConfig("format: json\n");

        $tester = $this->makeTester();
        $tester->execute(['report' => $this->fixturesDir . '/crap4j-valid.xml']);

        self::assertSame(0, $tester->getStatusCode());
        $data = json_decode($tester->getDisplay(), true);
        self::assertIsArray($data);
        self::assertArrayHasKey('threshold', $data);
    }

    public function testCliFormatOverridesConfig(): void
    {
        $this->writeConfig("format: json\n");

        $tester = $this->makeTester();
        $tester->execute([
            'report' => $this->fixturesDir . '/crap4j-valid.xml',
            '--format' => 'text',
        ]);

        self::assertStringContainsString('CRAP threshold OK', $tester->getDisplay());
        self::assertStringNotContainsString('"threshold"', $tester->getDisplay());
    }

    public function testConfigMaxViolationsIsUsedWhenNoCliOption(): void
    {
        $this->writeConfig("max_violations: 5\n");

        $tester = $this->makeTester();
        $tester->execute([
            'report' => $this->fixturesDir . '/crap4j-with-violations.xml',
            '--threshold' => '30',
        ]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('limit: 5', $tester->getDisplay());
    }

    public function testMalformedConfigReturnsExitCode7(): void
    {
        $this->writeConfig("threshold: [\ninvalid yaml");

        $tester = $this->makeTester();
        $tester->execute(['report' => $this->fixturesDir . '/crap4j-valid.xml']);

        self::assertSame(7, $tester->getStatusCode());
        self::assertStringContainsString('invalid YAML', $tester->getDisplay());
    }
}
