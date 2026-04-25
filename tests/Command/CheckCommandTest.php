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

    // --max-violations tests

    public function testMaxViolationsBelowLimitReturnsExitCode0(): void
    {
        $this->tester->execute([
            'report' => $this->fixturesDir . '/crap4j-with-violations.xml',
            '--threshold' => '30',
            '--max-violations' => '5',
        ]);

        self::assertSame(0, $this->tester->getStatusCode());
    }

    public function testMaxViolationsExactlyAtLimitReturnsExitCode0(): void
    {
        $this->tester->execute([
            'report' => $this->fixturesDir . '/crap4j-with-violations.xml',
            '--threshold' => '30',
            '--max-violations' => '3',
        ]);

        self::assertSame(0, $this->tester->getStatusCode());
    }

    public function testMaxViolationsAboveLimitReturnsExitCode1(): void
    {
        $this->tester->execute([
            'report' => $this->fixturesDir . '/crap4j-with-violations.xml',
            '--threshold' => '30',
            '--max-violations' => '2',
        ]);

        self::assertSame(1, $this->tester->getStatusCode());
    }

    public function testMaxViolationsZeroFailsOnAnyViolation(): void
    {
        $this->tester->execute([
            'report' => $this->fixturesDir . '/crap4j-with-violations.xml',
            '--threshold' => '30',
            '--max-violations' => '0',
        ]);

        self::assertSame(1, $this->tester->getStatusCode());
    }

    public function testMaxViolationsOutputShowsLimit(): void
    {
        $this->tester->execute([
            'report' => $this->fixturesDir . '/crap4j-with-violations.xml',
            '--threshold' => '30',
            '--max-violations' => '5',
        ]);

        self::assertStringContainsString('limit: 5', $this->tester->getDisplay());
    }

    public function testMaxViolationsNonNumericReturnsExitCode2(): void
    {
        $this->tester->execute([
            'report' => $this->fixturesDir . '/crap4j-with-violations.xml',
            '--max-violations' => 'abc',
        ]);

        self::assertSame(2, $this->tester->getStatusCode());
    }

    public function testWithoutMaxViolationsBehaviorUnchanged(): void
    {
        $this->tester->execute([
            'report' => $this->fixturesDir . '/crap4j-with-violations.xml',
            '--threshold' => '30',
        ]);

        self::assertSame(1, $this->tester->getStatusCode());
    }

    // --max-age tests

    private function makeTesterWithClock(\Closure $clock): CommandTester
    {
        $application = new Application();
        $application->add(new CheckCommand($clock));

        return new CommandTester($application->find('check'));
    }

    public function testMaxAgeWithFreshReportPasses(): void
    {
        $now = time();
        $file = $this->fixturesDir . '/crap4j-valid.xml';
        touch($file, $now - 300); // 5 minutes old

        $tester = $this->makeTesterWithClock(fn() => $now);
        $tester->execute([
            'report' => $file,
            '--max-age' => '60',
        ]);

        self::assertSame(0, $tester->getStatusCode());
    }

    public function testMaxAgeWithStaleReportReturnsExitCode6(): void
    {
        $now = time();
        $file = $this->fixturesDir . '/crap4j-valid.xml';
        touch($file, $now - 5400); // 90 minutes old

        $tester = $this->makeTesterWithClock(fn() => $now);
        $tester->execute([
            'report' => $file,
            '--max-age' => '60',
        ]);

        self::assertSame(6, $tester->getStatusCode());
    }

    public function testMaxAgeOutputContainsClearMessage(): void
    {
        $now = time();
        $file = $this->fixturesDir . '/crap4j-valid.xml';
        touch($file, $now - 5400); // 90 minutes old

        $tester = $this->makeTesterWithClock(fn() => $now);
        $tester->execute([
            'report' => $file,
            '--max-age' => '60',
        ]);

        $output = $tester->getDisplay();
        self::assertStringContainsString('stale', $output);
        self::assertStringContainsString('90', $output);
    }

    public function testMaxAgeHourFormat(): void
    {
        $now = time();
        $file = $this->fixturesDir . '/crap4j-valid.xml';
        touch($file, $now - 300); // 5 minutes old

        $tester = $this->makeTesterWithClock(fn() => $now);
        $tester->execute([
            'report' => $file,
            '--max-age' => '1h', // 60 minutes
        ]);

        self::assertSame(0, $tester->getStatusCode());
    }

    public function testMaxAgeMinuteFormat(): void
    {
        $now = time();
        $file = $this->fixturesDir . '/crap4j-valid.xml';
        touch($file, $now - 5400); // 90 minutes old

        $tester = $this->makeTesterWithClock(fn() => $now);
        $tester->execute([
            'report' => $file,
            '--max-age' => '30m',
        ]);

        self::assertSame(6, $tester->getStatusCode());
    }

    public function testMaxAgeInvalidValueReturnsExitCode2(): void
    {
        $this->tester->execute([
            'report' => $this->fixturesDir . '/crap4j-valid.xml',
            '--max-age' => 'yesterday',
        ]);

        self::assertSame(2, $this->tester->getStatusCode());
    }

    public function testWithoutMaxAgeNoStaleCheck(): void
    {
        $now = time();
        $file = $this->fixturesDir . '/crap4j-valid.xml';
        touch($file, $now - 99999); // very old

        $this->tester->execute(['report' => $file]);

        self::assertSame(0, $this->tester->getStatusCode());
    }
}
