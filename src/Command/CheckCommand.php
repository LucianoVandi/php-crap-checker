<?php

declare(strict_types=1);

namespace Lvandi\PhpCrapChecker\Command;

use LogicException;
use Lvandi\PhpCrapChecker\Analyzer\CrapAnalyzer;
use Lvandi\PhpCrapChecker\Console\ExitCode;
use Lvandi\PhpCrapChecker\Exception\InvalidReportException;
use Lvandi\PhpCrapChecker\Exception\ReportNotFoundException;
use Lvandi\PhpCrapChecker\Parser\Crap4jParser;
use Lvandi\PhpCrapChecker\Result\Violation;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class CheckCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('check')
            ->setDescription('Check CRAP score against a threshold')
            ->addArgument('report', InputArgument::OPTIONAL, 'Path to Crap4J XML report', 'build/crap4j.xml')
            ->addOption('threshold', null, InputOption::VALUE_REQUIRED, 'Maximum allowed CRAP score', '30')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format (text)', 'text');
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $reportPath = $input->getArgument('report');
        $thresholdRaw = $input->getOption('threshold');

        if (!is_string($reportPath) || !is_string($thresholdRaw)) {
            throw new LogicException('report and threshold must be strings');
        }

        if (!is_numeric($thresholdRaw)) {
            $output->writeln(sprintf('<error>Invalid threshold "%s": must be a number.</error>', $thresholdRaw));
            return ExitCode::InvalidInput->value;
        }

        $threshold = (float) $thresholdRaw;

        try {
            $methods = (new Crap4jParser())->parse($reportPath);
        } catch (ReportNotFoundException $e) {
            $output->writeln($e->getMessage());
            $output->writeln('');
            $output->writeln('Generate it with:');
            $output->writeln('php -d pcov.enabled=1 vendor/bin/phpunit --coverage-crap4j build/crap4j.xml');
            return ExitCode::ReportNotFound->value;
        } catch (InvalidReportException) {
            $output->writeln(sprintf('<error>Invalid XML report: %s</error>', $reportPath));
            return ExitCode::InvalidXml->value;
        }

        if ($methods === []) {
            $output->writeln('<comment>No methods found in report.</comment>');
            return ExitCode::NoMethodsFound->value;
        }

        $violations = (new CrapAnalyzer())->findViolations($methods, $threshold);
        $thresholdLabel = $this->formatNumber($threshold);

        if ($violations === []) {
            $output->writeln(sprintf('CRAP threshold OK. Max allowed: %s', $thresholdLabel));
            $output->writeln(sprintf('Analyzed methods: %d', count($methods)));
            $output->writeln('Violations: 0');
            return ExitCode::Success->value;
        }

        $output->writeln(sprintf('CRAP threshold exceeded. Max allowed: %s', $thresholdLabel));
        $output->writeln('');
        $output->writeln(sprintf('%d violation%s found:', count($violations), count($violations) === 1 ? '' : 's'));
        $output->writeln('');

        foreach ($violations as $i => $violation) {
            $this->writeViolation($output, $i + 1, $violation);
        }

        return ExitCode::ThresholdExceeded->value;
    }

    private function writeViolation(OutputInterface $output, int $index, Violation $violation): void
    {
        $method = $violation->method;
        $className = $method->className ?? 'unknown';
        $output->writeln(sprintf('%d) %s::%s()', $index, $className, $method->name));

        $file = $method->file ?? 'unknown';
        $line = $method->line !== null ? ':' . $method->line : '';
        $output->writeln(sprintf('   File: %s%s', $file, $line));

        $output->writeln(sprintf('   CRAP: %.2f', $method->crap));

        if ($method->complexity !== null) {
            $output->writeln(sprintf('   Complexity: %d', $method->complexity));
        }

        if ($method->coverage !== null) {
            $output->writeln(sprintf('   Coverage: %.2f%%', $method->coverage));
        }

        $output->writeln('');
    }

    private function formatNumber(float $value): string
    {
        return $value === (float)(int) $value ? (string)(int) $value : (string) $value;
    }
}
