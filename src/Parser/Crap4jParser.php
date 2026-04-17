<?php

declare(strict_types=1);

namespace Lvandi\PhpCrapChecker\Parser;

use Lvandi\PhpCrapChecker\Exception\InvalidReportException;
use Lvandi\PhpCrapChecker\Exception\ReportNotFoundException;
use Lvandi\PhpCrapChecker\ValueObject\MethodMetric;

final class Crap4jParser
{
    /**
     * @return list<MethodMetric>
     */
    public function parse(string $path): array
    {
        if (!file_exists($path) || !is_readable($path)) {
            throw ReportNotFoundException::forPath($path);
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_file($path);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false || $errors !== []) {
            throw InvalidReportException::malformedXml($path);
        }

        return $this->extractMethods($xml);
    }

    /**
     * @return list<MethodMetric>
     */
    private function extractMethods(\SimpleXMLElement $xml): array
    {
        $methods = [];

        foreach ($xml->project->package ?? [] as $package) {
            $className = (string) $package['name'];

            foreach ($package->class ?? [] as $class) {
                $className = (string) $class['name'];

                foreach ($class->method ?? [] as $method) {
                    $metric = $this->buildMetric($method, $className);

                    if ($metric !== null) {
                        $methods[] = $metric;
                    }
                }
            }
        }

        return $methods;
    }

    private function buildMetric(\SimpleXMLElement $method, string $className): ?MethodMetric
    {
        $name = (string) $method['name'];

        if ((string) $method->crap === '') {
            return null;
        }

        $crap = (float) $method->crap;
        $file = (string) $method->relative_path !== '' ? (string) $method->relative_path : null;
        $line = (string) $method->start_line !== '' ? (int) $method->start_line : null;
        $complexity = (string) $method->complexity !== '' ? (int) $method->complexity : null;
        $coverage = (string) $method->covered_percent !== '' ? (float) $method->covered_percent : null;

        return new MethodMetric(
            name: $name,
            crap: $crap,
            className: $className !== '' ? $className : null,
            file: $file,
            line: $line,
            complexity: $complexity,
            coverage: $coverage,
        );
    }
}
