<?php

declare(strict_types=1);

namespace Lvandi\PhpCrapChecker\Command;

use Lvandi\PhpCrapChecker\Console\ExitCode;
use SimpleXMLElement;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class DoctorCommand extends Command
{
    /** @param list<string>|null $loadedExtensions */
    public function __construct(
        private readonly string $workingDir = '',
        private readonly ?array $loadedExtensions = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('doctor')
            ->setDescription('Diagnose environment and PHPUnit configuration');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('PHP CRAP Checker — Doctor');
        $output->writeln('');

        $extensions = $this->loadedExtensions !== null
            ? array_map('strtolower', $this->loadedExtensions)
            : array_map('strtolower', get_loaded_extensions());
        $hasFail = false;

        $output->writeln('PHP Runtime');
        $output->writeln(sprintf('  <info>[OK]</info>   PHP %s', PHP_VERSION));
        $output->writeln('');

        $output->writeln('Extensions');

        if (in_array('simplexml', $extensions, true)) {
            $output->writeln('  <info>[OK]</info>   ext-simplexml loaded');
        } else {
            $output->writeln('  <error>[FAIL]</error> ext-simplexml not found');
            $output->writeln('     → Enable ext-simplexml in your php.ini');
            $hasFail = true;
        }

        $hasPcov = in_array('pcov', $extensions, true);
        $hasXdebug = in_array('xdebug', $extensions, true);

        if ($hasPcov || $hasXdebug) {
            $driver = $hasPcov ? 'PCOV' : 'Xdebug';
            $output->writeln(sprintf('  <info>[OK]</info>   Coverage driver found: %s', $driver));
        } else {
            $output->writeln('  <comment>[WARN]</comment> No coverage driver (PCOV or Xdebug) detected');
            $output->writeln('     → Install PCOV: composer require --dev pcov/clobber');
        }

        $output->writeln('');

        $cwd = $this->workingDir !== '' ? $this->workingDir : (string) getcwd();

        $output->writeln('PHPUnit Configuration');

        $configPath = $this->findPhpunitConfig($cwd);

        if ($configPath === null) {
            $output->writeln('  <comment>[WARN]</comment> phpunit.xml or phpunit.xml.dist not found');
            $output->writeln(sprintf('     → Expected in: %s', $cwd));
            $output->writeln('');

            return $hasFail ? ExitCode::ThresholdExceeded->value : ExitCode::Success->value;
        }

        $output->writeln(sprintf('  <info>[OK]</info>   %s found', basename($configPath)));

        $xml = @simplexml_load_file($configPath);

        if ($xml === false) {
            $output->writeln(sprintf('  <error>[FAIL]</error> Failed to parse %s', basename($configPath)));
            $output->writeln('');

            return ExitCode::ThresholdExceeded->value;
        }

        $crap4jPath = $this->findCrap4jPath($xml);

        if ($crap4jPath === null) {
            $output->writeln('  <comment>[WARN]</comment> Crap4J report not configured');
            $output->writeln('    => Add <crap4j outputFile="build/crap4j.xml"/> inside <coverage><report>');
            $output->writeln('');

            return $hasFail ? ExitCode::ThresholdExceeded->value : ExitCode::Success->value;
        }

        $output->writeln(sprintf('  <info>[OK]</info>   Crap4J report configured: %s', $crap4jPath));
        $output->writeln('');

        $output->writeln('Report Path');

        $defaultReport = 'build/crap4j.xml';

        if ($crap4jPath === $defaultReport) {
            $output->writeln(sprintf('  <info>[OK]</info>   Default report path matches PHPUnit config (%s)', $defaultReport));
        } else {
            $output->writeln(sprintf(
                '  <comment>[WARN]</comment> PHPUnit writes to "%s" but checker default is "%s"',
                $crap4jPath,
                $defaultReport,
            ));
            $output->writeln(sprintf('     → Run: crap-check check %s', $crap4jPath));
        }

        $output->writeln('');

        return $hasFail ? ExitCode::ThresholdExceeded->value : ExitCode::Success->value;
    }

    private function findPhpunitConfig(string $dir): ?string
    {
        foreach (['phpunit.xml', 'phpunit.xml.dist'] as $filename) {
            $path = $dir . '/' . $filename;
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    private function findCrap4jPath(SimpleXMLElement $xml): ?string
    {
        $nodes = $xml->xpath('coverage/report/crap4j/@outputFile');

        if (is_array($nodes) && $nodes !== []) {
            $path = (string) $nodes[0];
            if ($path !== '') {
                return $path;
            }
        }

        return null;
    }
}
