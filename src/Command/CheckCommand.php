<?php

declare(strict_types=1);

namespace Lvandi\PhpCrapChecker\Command;

use JsonException;
use Lvandi\PhpCrapChecker\Analyzer\CrapAnalyzer;
use Lvandi\PhpCrapChecker\Config\Configuration;
use Lvandi\PhpCrapChecker\Config\ConfigLoader;
use Lvandi\PhpCrapChecker\Console\ExitCode;
use Lvandi\PhpCrapChecker\Exception\InvalidConfigException;
use Lvandi\PhpCrapChecker\Exception\InvalidReportException;
use Lvandi\PhpCrapChecker\Exception\ReportNotFoundException;
use Lvandi\PhpCrapChecker\Parser\Crap4jParser;
use Lvandi\PhpCrapChecker\Result\Violation;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
final class CheckCommand extends Command
{
    /**
     * @param (\Closure(): int)|null $clock
     */
    public function __construct(
        private readonly ?\Closure $clock = null,
        private readonly ?string $cwd = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('check')
            ->setDescription('Check CRAP score against a threshold')
            ->addArgument('report', InputArgument::OPTIONAL, 'Path to Crap4J XML report')
            ->addOption('threshold', null, InputOption::VALUE_REQUIRED, 'Maximum allowed CRAP score')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format (text|json)')
            ->addOption('max-violations', null, InputOption::VALUE_REQUIRED, 'Maximum number of tolerated violations')
            ->addOption('max-age', null, InputOption::VALUE_REQUIRED, 'Maximum report age in minutes (e.g. 60, 30m, 2h)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $config = (new ConfigLoader())->load($this->cwd ?? (string) getcwd());
        } catch (InvalidConfigException $e) {
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
            return ExitCode::InvalidConfig->value;
        }

        $options = $this->resolveOptions($input, $output, $config);

        if (is_int($options)) {
            return $options;
        }

        if ($options->maxAgeSeconds !== null) {
            $staleResult = $this->checkAge($options->reportPath, $options->maxAgeSeconds, $output);
            if ($staleResult !== null) {
                return $staleResult;
            }
        }

        try {
            $methods = (new Crap4jParser())->parse($options->reportPath);
        } catch (ReportNotFoundException $e) {
            $output->writeln($e->getMessage());
            $output->writeln('');
            $output->writeln('Generate it with:');
            $output->writeln('php -d pcov.enabled=1 vendor/bin/phpunit --coverage-crap4j build/crap4j.xml');
            return ExitCode::ReportNotFound->value;
        } catch (InvalidReportException) {
            $output->writeln(sprintf('<error>Invalid XML report: %s</error>', $options->reportPath));
            return ExitCode::InvalidXml->value;
        }

        if ($methods === []) {
            $output->writeln('<comment>No methods found in report.</comment>');
            return ExitCode::NoMethodsFound->value;
        }

        $violations = (new CrapAnalyzer())->findViolations($methods, $options->threshold);

        if ($options->format === 'json') {
            $output->writeln($this->encodeJson($options->threshold, count($methods), $violations));
            return $this->resolveExitCode($violations, $options->maxViolations);
        }

        return $this->renderTextOutput($output, $violations, $options->threshold, $options->maxViolations, count($methods));
    }

    private function resolveOptions(InputInterface $input, OutputInterface $output, Configuration $config): ResolvedOptions|int
    {
        $reportArg = $input->getArgument('report');
        $reportPath = is_string($reportArg) ? $reportArg : ($config->report ?? 'build/crap4j.xml');

        $threshold = $this->resolveThreshold($input, $config, $output);
        if ($threshold === false) {
            return ExitCode::InvalidInput->value;
        }

        $format = $this->resolveFormat($input, $config, $output);
        if ($format === false) {
            return ExitCode::InvalidInput->value;
        }

        $maxViolations = $this->resolveMaxViolations($input, $config, $output);
        if ($maxViolations === false) {
            return ExitCode::InvalidInput->value;
        }

        $maxAgeSeconds = $this->resolveMaxAge($input, $output);
        if ($maxAgeSeconds === false) {
            return ExitCode::InvalidInput->value;
        }

        return new ResolvedOptions(
            reportPath: $reportPath,
            threshold: $threshold,
            format: $format,
            maxViolations: $maxViolations,
            maxAgeSeconds: $maxAgeSeconds,
        );
    }

    private function resolveThreshold(InputInterface $input, Configuration $config, OutputInterface $output): float|false
    {
        $raw = $input->getOption('threshold');
        if (!is_string($raw)) {
            $raw = $config->threshold !== null ? (string) $config->threshold : '30';
        }

        if (!is_numeric($raw)) {
            $output->writeln(sprintf('<error>Invalid threshold "%s": must be a number.</error>', $raw));
            return false;
        }

        return (float) $raw;
    }

    private function resolveFormat(InputInterface $input, Configuration $config, OutputInterface $output): string|false
    {
        $format = $input->getOption('format');
        if (!is_string($format)) {
            $format = $config->format ?? 'text';
        }

        if (!in_array($format, ['text', 'json'], true)) {
            $output->writeln(sprintf('<error>Invalid format "%s": must be "text" or "json".</error>', $format));
            return false;
        }

        return $format;
    }

    private function resolveMaxViolations(InputInterface $input, Configuration $config, OutputInterface $output): int|null|false
    {
        $raw = $input->getOption('max-violations');
        if ($raw === null && $config->maxViolations !== null) {
            $raw = (string) $config->maxViolations;
        }

        if ($raw === null) {
            return null;
        }

        assert(is_string($raw));

        if (!is_numeric($raw) || (int) $raw < 0) {
            $output->writeln(sprintf('<error>Invalid --max-violations "%s": must be a non-negative integer.</error>', $raw));
            return false;
        }

        return (int) $raw;
    }

    private function resolveMaxAge(InputInterface $input, OutputInterface $output): int|null|false
    {
        $raw = $input->getOption('max-age');

        if ($raw === null) {
            return null;
        }

        if (!is_string($raw)) {
            $output->writeln('<error>Invalid --max-age value.</error>');
            return false;
        }

        $seconds = $this->parseAge($raw);

        if ($seconds === null) {
            $output->writeln(sprintf('<error>Invalid --max-age "%s": use minutes (e.g. 60) or a duration like 30m or 2h.</error>', $raw));
            return false;
        }

        return $seconds;
    }

    /**
     * @param list<Violation> $violations
     */
    private function renderTextOutput(
        OutputInterface $output,
        array $violations,
        float $threshold,
        ?int $maxViolations,
        int $totalMethods,
    ): int {
        $thresholdLabel = $this->formatNumber($threshold);

        if ($violations === []) {
            $output->writeln(sprintf('CRAP threshold OK. Max allowed: %s', $thresholdLabel));
            $output->writeln(sprintf('Analyzed methods: %d', $totalMethods));
            $output->writeln('Violations: 0');
            return ExitCode::Success->value;
        }

        $output->writeln(sprintf('CRAP threshold exceeded. Max allowed: %s', $thresholdLabel));
        $output->writeln('');

        $count = count($violations);
        $limit = $maxViolations !== null ? sprintf(' (limit: %d)', $maxViolations) : '';
        $output->writeln(sprintf('%d violation%s found%s:', $count, $count === 1 ? '' : 's', $limit));
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

        $now = $this->clock instanceof \Closure ? ($this->clock)() : time();
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
            'methods' => array_map($this->violationToArray(...), $violations),
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
