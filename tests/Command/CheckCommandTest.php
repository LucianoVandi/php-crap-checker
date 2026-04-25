<?php

declare(strict_types=1);

namespace Lvandi\PhpCrapChecker\Tests\Command;

use Lvandi\PhpCrapChecker\Command\CheckCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class CheckCommandTest extends TestCase
{
    private CommandTester $tester;
    private string $fixturesDir;

    protected function setUp(): void
    {
        $application = new Application();
        $application->add(new CheckCommand());
        $this->tester = new CommandTester($application->find('check'));
        $this->fixturesDir = __DIR__ . '/../Fixtures';
    }

    public function testValidReportWithNoViolationsReturnsExitCode0(): void
    {
        $this->tester->execute([
            'report' => $this->fixturesDir . '/crap4j-valid.xml',
            '--threshold' => '30',
        ]);

        self::assertSame(0, $this->tester->getStatusCode());
        $output = $this->tester->getDisplay();
        self::assertStringContainsString('CRAP threshold OK. Max allowed: 30', $output);
        self::assertStringContainsString('Analyzed methods: 3', $output);
        self::assertStringContainsString('Violations: 0', $output);
    }

    public function testValidReportWithViolationsReturnsExitCode1(): void
    {
        $this->tester->execute([
            'report' => $this->fixturesDir . '/crap4j-with-violations.xml',
            '--threshold' => '30',
        ]);

        self::assertSame(1, $this->tester->getStatusCode());
        $output = $this->tester->getDisplay();
        self::assertStringContainsString('CRAP threshold exceeded. Max allowed: 30', $output);
        self::assertStringContainsString('3 violations found', $output);
    }

    public function testViolationsAreListedInCrapDescOrder(): void
    {
        $this->tester->execute([
            'report' => $this->fixturesDir . '/crap4j-with-violations.xml',
            '--threshold' => '30',
        ]);

        $output = $this->tester->getDisplay();
        $posGenerate = strpos($output, 'generate');
        $posImport = strpos($output, 'import');
        $posCalculate = strpos($output, 'calculate');

        self::assertNotFalse($posGenerate);
        self::assertNotFalse($posImport);
        self::assertNotFalse($posCalculate);
        self::assertLessThan($posImport, $posGenerate);
        self::assertLessThan($posCalculate, $posImport);
    }

    public function testViolationOutputContainsDetails(): void
    {
        $this->tester->execute([
            'report' => $this->fixturesDir . '/crap4j-with-violations.xml',
            '--threshold' => '30',
        ]);

        $output = $this->tester->getDisplay();
        self::assertStringContainsString('CRAP: 72.00', $output);
        self::assertStringContainsString('Complexity: 18', $output);
        self::assertStringContainsString('Coverage: 0.00%', $output);
        self::assertStringContainsString('File: unknown', $output);
        self::assertStringContainsString('App\Legacy\ReportGenerator::generate()', $output);
    }

    public function testMissingReportReturnsExitCode3(): void
    {
        $this->tester->execute([
            'report' => '/non/existent/report.xml',
            '--threshold' => '30',
        ]);

        self::assertSame(3, $this->tester->getStatusCode());
        self::assertStringContainsString('Report not found', $this->tester->getDisplay());
    }

    public function testMissingReportOutputsGenerateHint(): void
    {
        $this->tester->execute([
            'report' => '/non/existent/report.xml',
            '--threshold' => '30',
        ]);

        self::assertStringContainsString('Generate it with:', $this->tester->getDisplay());
    }

    public function testNonNumericThresholdReturnsExitCode2(): void
    {
        $this->tester->execute([
            'report' => $this->fixturesDir . '/crap4j-valid.xml',
            '--threshold' => 'notanumber',
        ]);

        self::assertSame(2, $this->tester->getStatusCode());
    }

    public function testInvalidXmlReturnsExitCode4(): void
    {
        $malformedFile = sys_get_temp_dir() . '/malformed-crap4j-' . uniqid() . '.xml';
        file_put_contents($malformedFile, '<?xml version="1.0"?><unclosed>');

        try {
            $this->tester->execute([
                'report' => $malformedFile,
                '--threshold' => '30',
            ]);
            self::assertSame(4, $this->tester->getStatusCode());
        } finally {
            @unlink($malformedFile);
        }
    }

    public function testEmptyReportReturnsExitCode5(): void
    {
        $this->tester->execute([
            'report' => $this->fixturesDir . '/crap4j-empty.xml',
            '--threshold' => '30',
        ]);

        self::assertSame(5, $this->tester->getStatusCode());
    }

    public function testDefaultThresholdIs30(): void
    {
        $this->tester->execute([
            'report' => $this->fixturesDir . '/crap4j-valid.xml',
        ]);

        self::assertSame(0, $this->tester->getStatusCode());
        self::assertStringContainsString('Max allowed: 30', $this->tester->getDisplay());
    }

    public function testJsonFormatWithNoViolationsReturnsExitCode0(): void
    {
        $this->tester->execute([
            'report' => $this->fixturesDir . '/crap4j-valid.xml',
            '--threshold' => '30',
            '--format' => 'json',
        ]);

        self::assertSame(0, $this->tester->getStatusCode());
        $data = json_decode($this->tester->getDisplay(), true);
        self::assertIsArray($data);
        self::assertSame(30.0, $data['threshold']);
        self::assertSame(0, $data['violations']);
        self::assertSame([], $data['methods']);
    }

    public function testJsonFormatWithViolationsReturnsExitCode1(): void
    {
        $this->tester->execute([
            'report' => $this->fixturesDir . '/crap4j-with-violations.xml',
            '--threshold' => '30',
            '--format' => 'json',
        ]);

        self::assertSame(1, $this->tester->getStatusCode());
        $data = json_decode($this->tester->getDisplay(), true);
        self::assertIsArray($data);
        self::assertSame(30.0, $data['threshold']);
        self::assertSame(3, $data['violations']);
        self::assertIsArray($data['methods']);
        self::assertCount(3, $data['methods']);
        $first = $data['methods'][0];
        self::assertIsArray($first);
        self::assertArrayHasKey('name', $first);
        self::assertArrayHasKey('crap', $first);
    }

    public function testInvalidFormatReturnsExitCode2(): void
    {
        $this->tester->execute([
            'report' => $this->fixturesDir . '/crap4j-valid.xml',
            '--format' => 'csv',
        ]);

        self::assertSame(2, $this->tester->getStatusCode());
        self::assertStringContainsString('Invalid format', $this->tester->getDisplay());
    }
}
