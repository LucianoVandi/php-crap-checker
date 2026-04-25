<?php

declare(strict_types=1);

namespace Lvandi\PhpCrapChecker\Tests\Command;

use Lvandi\PhpCrapChecker\Command\DoctorCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class DoctorCommandTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/crap-doctor-' . uniqid();
        mkdir($this->tempDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tempDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->tempDir);
    }

    /** @param list<string>|null $loadedExtensions */
    private function makeTester(string $workingDir, ?array $loadedExtensions = null): CommandTester
    {
        $application = new Application();
        $application->add(new DoctorCommand($workingDir, $loadedExtensions));

        return new CommandTester($application->find('doctor'));
    }

    public function testShowsPhpVersion(): void
    {
        $tester = $this->makeTester($this->tempDir);
        $tester->execute([]);

        self::assertStringContainsString('PHP ' . PHP_VERSION, $tester->getDisplay());
    }

    public function testSimpleXmlLoadedShown(): void
    {
        $tester = $this->makeTester($this->tempDir, ['simplexml', 'pcov']);
        $tester->execute([]);

        self::assertStringContainsString('[OK]', $tester->getDisplay());
        self::assertStringContainsString('ext-simplexml loaded', $tester->getDisplay());
    }

    public function testSimpleXmlMissingShowsFailAndExitCode1(): void
    {
        $tester = $this->makeTester($this->tempDir, ['pcov']);
        $tester->execute([]);

        self::assertStringContainsString('[FAIL]', $tester->getDisplay());
        self::assertStringContainsString('ext-simplexml not found', $tester->getDisplay());
        self::assertSame(1, $tester->getStatusCode());
    }

    public function testCoverageDriverPcovFound(): void
    {
        $tester = $this->makeTester($this->tempDir, ['simplexml', 'pcov']);
        $tester->execute([]);

        self::assertStringContainsString('PCOV', $tester->getDisplay());
    }

    public function testCoverageDriverXdebugFound(): void
    {
        $tester = $this->makeTester($this->tempDir, ['simplexml', 'xdebug']);
        $tester->execute([]);

        self::assertStringContainsString('Xdebug', $tester->getDisplay());
    }

    public function testNoCoverageDriverShowsWarning(): void
    {
        $tester = $this->makeTester($this->tempDir, ['simplexml']);
        $tester->execute([]);

        self::assertStringContainsString('[WARN]', $tester->getDisplay());
        self::assertStringContainsString('No coverage driver', $tester->getDisplay());
        self::assertSame(0, $tester->getStatusCode());
    }

    public function testPhpunitNotFoundShowsWarning(): void
    {
        $tester = $this->makeTester($this->tempDir, ['simplexml', 'pcov']);
        $tester->execute([]);

        $output = $tester->getDisplay();
        self::assertStringContainsString('phpunit.xml', $output);
        self::assertStringContainsString('not found', $output);
        self::assertSame(0, $tester->getStatusCode());
    }

    public function testPhpunitXmlFoundPreferredOverDist(): void
    {
        file_put_contents($this->tempDir . '/phpunit.xml', $this->makePhpunitXml('build/crap4j.xml'));
        file_put_contents($this->tempDir . '/phpunit.xml.dist', $this->makePhpunitXml('build/other.xml'));

        $tester = $this->makeTester($this->tempDir, ['simplexml', 'pcov']);
        $tester->execute([]);

        $output = $tester->getDisplay();
        self::assertStringContainsString('phpunit.xml found', $output);
        self::assertStringNotContainsString('phpunit.xml.dist found', $output);
    }

    public function testPhpunitDistFoundWhenNoXml(): void
    {
        file_put_contents($this->tempDir . '/phpunit.xml.dist', $this->makePhpunitXml('build/crap4j.xml'));

        $tester = $this->makeTester($this->tempDir, ['simplexml', 'pcov']);
        $tester->execute([]);

        self::assertStringContainsString('phpunit.xml.dist found', $tester->getDisplay());
    }

    public function testCrap4jConfiguredShowsPath(): void
    {
        file_put_contents($this->tempDir . '/phpunit.xml', $this->makePhpunitXml('build/crap4j.xml'));

        $tester = $this->makeTester($this->tempDir, ['simplexml', 'pcov']);
        $tester->execute([]);

        self::assertStringContainsString('build/crap4j.xml', $tester->getDisplay());
    }

    public function testCrap4jNotConfiguredShowsWarning(): void
    {
        file_put_contents($this->tempDir . '/phpunit.xml', $this->makePhpunitXmlNoCrap4j());

        $tester = $this->makeTester($this->tempDir, ['simplexml', 'pcov']);
        $tester->execute([]);

        self::assertStringContainsString('Crap4J report not configured', $tester->getDisplay());
        self::assertSame(0, $tester->getStatusCode());
    }

    public function testCrap4jPathMatchesDefaultExitCode0(): void
    {
        file_put_contents($this->tempDir . '/phpunit.xml', $this->makePhpunitXml('build/crap4j.xml'));

        $tester = $this->makeTester($this->tempDir, ['simplexml', 'pcov']);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Default report path matches', $tester->getDisplay());
    }

    public function testCrap4jPathMismatchShowsHint(): void
    {
        file_put_contents($this->tempDir . '/phpunit.xml', $this->makePhpunitXml('build/custom-crap.xml'));

        $tester = $this->makeTester($this->tempDir, ['simplexml', 'pcov']);
        $tester->execute([]);

        $output = $tester->getDisplay();
        self::assertStringContainsString('build/custom-crap.xml', $output);
        self::assertStringContainsString('crap-check check build/custom-crap.xml', $output);
    }

    public function testExitCode0WhenEverythingOk(): void
    {
        file_put_contents($this->tempDir . '/phpunit.xml', $this->makePhpunitXml('build/crap4j.xml'));

        $tester = $this->makeTester($this->tempDir, ['simplexml', 'pcov']);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
    }


    private function makePhpunitXml(string $crap4jPath): string
    {
        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <phpunit>
                <coverage>
                    <report>
                        <crap4j outputFile="{$crap4jPath}"/>
                    </report>
                </coverage>
            </phpunit>
            XML;
    }

    private function makePhpunitXmlNoCrap4j(): string
    {
        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <phpunit>
                <coverage/>
            </phpunit>
            XML;
    }


}
