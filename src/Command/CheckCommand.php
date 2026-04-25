<?php

declare(strict_types=1);

namespace Lvandi\PhpCrapChecker\Command;

use JsonException;
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
    /** @param (\Closure(): int)|null $clock */
    public function __construct(
        private readonly ?\Closure $clock = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('check')
            ->setDescription('Check CRAP score against a threshold')
            ->addArgument('report', InputArgument::OPTIONAL, 'Path to Crap4J XML report', 'build/crap4j.xml')
            ->addOption('threshold', null, InputOption::VALUE_REQUIRED, 'Maximum allowed CRAP score', '30')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format (text|json)', 'text')
            ->addOption('max-violations', null, InputOption::VALUE_REQUIRED, 'Maximum number of tolerated violations')
            ->addOption('max-age', null, InputOption::VALUE_REQUIRED, 'Maximum report age in minutes (e.g. 60, 30m, 2h)');
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $reportPath = $input->getArgument('report');
        $thresholdRaw = $input->getOption('threshold');
        $format = $input->getOption('format');
        $maxViolationsRaw = $input->getOption('max-violations');
        $maxAgeRaw = $input->getOption('max-age');

        if (!is_string($reportPath) || !is_string($thresholdRaw) || !is_string($format)) {
            throw new LogicException('report, threshold and format must be strings');
        }

        if (!is_numeric($thresholdRaw)) {
            $output->writeln(sprintf('<error>Invalid threshold "%s": must be a number.</error>', $thresholdRaw));
            return ExitCode::InvalidInput->value;
        }

        if (!in_array($format, ['text', 'json'], true)) {
            $output->writeln(sprintf('<error>Invalid format "%s": must be "text" or "json".</error>', $format));
            return ExitCode::InvalidInput->value;
        }

        $maxViolations = null;

        if ($maxViolationsRaw !== null) {
            if (!is_string($maxViolationsRaw)) {
                throw new LogicException('max-violations must be a string');
            }
            if (!is_numeric($maxViolationsRaw) || (int) $maxViolationsRaw < 0) {
                $output->writeln(sprintf('<error>Invalid --max-violations "%s": must be a non-negative integer.</error>', $maxViolationsRaw));
                return ExitCode::InvalidInput->value;
            }
            $maxViolations = (int) $maxViolationsRaw;
        }

        $maxAgeSeconds = null;

        if ($maxAgeRaw !== null) {
            if (!is_string($maxAgeRaw)) {
                $output->writeln('<error>Invalid --max-age value.</error>');
                return ExitCode::InvalidInput->value;
            }
            $maxAgeSeconds = $this->parseAge($maxAgeRaw);
            if ($maxAgeSeconds === null) {
                $output->writeln(sprintf('<error>Invalid --max-age "%s": use minutes (e.g. 60) or a duration like 30m or 2h.</error>', $maxAgeRaw));
                return ExitCode::InvalidInput->value;
            }
        }

        $threshold = (float) $thresholdRaw;

        if ($maxAgeSeconds !== null) {
            $staleResult = $this->checkAge($reportPath, $maxAgeSeconds, $output);
            if ($staleResult !== null) {
                return $staleResult;
            }
        }

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

        if ($format === 'json') {
            $output->writeln($this->encodeJson($threshold, count($methods), $violations));
            return $this->resolveExitCode($violations, $maxViolations);
        }

        $thresholdLabel = $this->formatNumber($threshold);

        if ($violations === []) {
            $output->writeln(sprintf('CRAP threshold OK. Max allowed: %s', $thresholdLabel));
            $output->writeln(sprintf('Analyzed methods: %d', count($methods)));
            $output->writeln('Violations: 0');
            return ExitCode::Success->value;
        }

        $output->writeln(sprintf('CRAP threshold exceeded. Max allowed: %s', $thresholdLabel));
        $output->writeln('');

        $count = count($violations);

        if ($maxViolations !== null) {
            $output->writeln(sprintf('%d violation%s found (limit: %d):', $count, $count === 1 ? '' : 's', $maxViolations));
        } else {
            $output->writeln(sprintf('%d violation%s found:', $count, $count === 1 ? '' : 's'));
        }

        $output->writeln('');

        foreach ($violations as $i => $violation) {
            $this->writeViolation($output, $i + 1, $violation);
        }

        return $this->resolveExitCode($violations, $maxViolations);
    }

    /**
     * @param list<Violation> $violations
     */
    private function resolveExitCode(array $violations, ?int $maxViolations): int
    {
        if ($violations === []) {
            return ExitCode::Success->value;
        }

        if ($maxViolations !== null && count($violations) <= $maxViolations) {
            return ExitCode::Success->value;
        }

        return ExitCode::ThresholdExceeded->value;
    }

    private function checkAge(string $reportPath, int $maxAgeSeconds, OutputInterface $output): ?int
    {
        if (!file_exists($reportPath)) {
            return null;
        }

        $mtime = filemtime($reportPath);

        if ($mtime === false) {
            return null;
        }

        $now = $this->clock !== null ? ($this->clock)() : time();
        $ageSeconds = $now - $mtime;

        if ($ageSeconds > $maxAgeSeconds) {
            $ageMinutes = (int) round($ageSeconds / 60);
            $maxMinutes = (int) round($maxAgeSeconds / 60);
            $output->writeln(sprintf(
                '<error>Report is stale: generated %d minute%s ago (max: %d).</error>',
                $ageMinutes,
                $ageMinutes === 1 ? '' : 's',
                $maxMinutes,
            ));
            return ExitCode::StaleReport->value;
        }

        return null;
    }

    private function parseAge(string $value): ?int
    {
        if (is_numeric($value) && (int) $value > 0) {
            return (int) $value * 60;
        }

        if (preg_match('/^(\d+)m$/', $value, $matches)) {
            return (int) $matches[1] * 60;
        }

        if (preg_match('/^(\d+)h$/', $value, $matches)) {
            return (int) $matches[1] * 3600;
        }

        return null;
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

    /**
     * @param list<Violation> $violations
     * @throws JsonException
     */
    private function encodeJson(float $threshold, int $totalMethods, array $violations): string
    {
        $data = [
            'threshold' => $threshold,
            'analyzed' => $totalMethods,
            'violations' => count($violations),
            'methods' => array_map(fn (Violation $v): array => $this->violationToArray($v), $violations),
        ];

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private function violationToArray(Violation $violation): array
    {
        $method = $violation->method;
        $data = ['name' => $method->name, 'crap' => $method->crap];

        if ($method->className !== null) {
            $data['class_name'] = $method->className;
        }
        if ($method->file !== null) {
            $data['file'] = $method->file;
        }
        if ($method->line !== null) {
            $data['line'] = $method->line;
        }
        if ($method->complexity !== null) {
            $data['complexity'] = $method->complexity;
        }
        if ($method->coverage !== null) {
            $data['coverage'] = $method->coverage;
        }

        return $data;
    }

    private function formatNumber(float $value): string
    {
        return $value === (float)(int) $value ? (string)(int) $value : (string) $value;
    }
}
