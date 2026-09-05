<?php

declare(strict_types=1);

namespace Lvandi\PhpCrapChecker\Config;

use Lvandi\PhpCrapChecker\Exception\InvalidConfigException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class ConfigLoader
{
    private const string CONFIG_FILE = '.crap-checker.yml';
    private const int MAX_FILE_SIZE_BYTES = 65536;

    public function load(string $cwd): Configuration
    {
        $path = rtrim($cwd, '/') . '/' . self::CONFIG_FILE;

        if (!file_exists($path)) {
            return new Configuration();
        }

        $size = filesize($path);

        if ($size !== false && $size > self::MAX_FILE_SIZE_BYTES) {
            throw InvalidConfigException::tooLarge($path, self::MAX_FILE_SIZE_BYTES);
        }

        $raw = $this->parseYaml($path);

        if ($raw === null || $raw === []) {
            return new Configuration();
        }

        return $this->buildConfiguration($path, $raw);
    }

    /**
     * @return array<mixed>|null
     */
    private function parseYaml(string $path): ?array
    {
        try {
            $result = Yaml::parseFile($path);
        } catch (ParseException $e) {
            throw InvalidConfigException::malformedYaml($path, $e->getMessage());
        }

        if ($result === null) {
            return null;
        }

        if (!is_array($result)) {
            throw InvalidConfigException::malformedYaml($path, 'root element must be a mapping');
        }

        return $result;
    }

    /**
     * @param array<mixed> $raw
     */
    private function buildConfiguration(string $path, array $raw): Configuration
    {
        $report = $this->extractString($path, $raw, 'report');
        $threshold = $this->extractFloat($path, $raw, 'threshold');
        $format = $this->extractString($path, $raw, 'format');
        $maxViolations = $this->extractInt($path, $raw, 'max_violations');
        [$ignorePaths, $ignoreMethods] = $this->extractIgnore($path, $raw);

        return new Configuration(
            report: $report,
            threshold: $threshold,
            format: $format,
            maxViolations: $maxViolations,
            ignorePaths: $ignorePaths,
            ignoreMethods: $ignoreMethods,
        );
    }

    private function extractString(string $path, mixed $raw, string $key): ?string
    {
        if (!is_array($raw) || !array_key_exists($key, $raw)) {
            return null;
        }

        $value = $raw[$key];

        if (!is_string($value)) {
            throw InvalidConfigException::invalidField($path, $key, 'a string');
        }

        return $value;
    }

    private function extractFloat(string $path, mixed $raw, string $key): ?float
    {
        if (!is_array($raw) || !array_key_exists($key, $raw)) {
            return null;
        }

        $value = $raw[$key];

        if (!is_int($value) && !is_float($value)) {
            throw InvalidConfigException::invalidField($path, $key, 'a number');
        }

        return (float) $value;
    }

    private function extractInt(string $path, mixed $raw, string $key): ?int
    {
        if (!is_array($raw) || !array_key_exists($key, $raw)) {
            return null;
        }

        $value = $raw[$key];

        if (!is_int($value) || $value < 0) {
            throw InvalidConfigException::invalidField($path, $key, 'a non-negative integer');
        }

        return $value;
    }

    /**
     * @return array{list<string>, list<string>}
     */
    private function extractIgnore(string $path, mixed $raw): array
    {
        if (!is_array($raw) || !array_key_exists('ignore', $raw)) {
            return [[], []];
        }

        $ignore = $raw['ignore'];

        if (!is_array($ignore)) {
            throw InvalidConfigException::invalidField($path, 'ignore', 'a mapping');
        }

        $paths = $this->extractStringList($path, $ignore, 'paths');
        $methods = $this->extractStringList($path, $ignore, 'methods');

        return [$paths, $methods];
    }

    /**
     * @return list<string>
     */
    private function extractStringList(string $path, mixed $parent, string $key): array
    {
        if (!is_array($parent) || !array_key_exists($key, $parent)) {
            return [];
        }

        $list = $parent[$key];

        if (!is_array($list)) {
            throw InvalidConfigException::invalidField($path, "ignore.{$key}", 'a list of strings');
        }

        foreach ($list as $item) {
            if (!is_string($item)) {
                throw InvalidConfigException::invalidField($path, "ignore.{$key}", 'a list of strings');
            }
        }

        /** @var list<string> $list */
        return $list;
    }
}
