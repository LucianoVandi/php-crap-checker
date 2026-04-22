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

        foreach ($xml->methods->method ?? [] as $method) {
            $metric = $this->buildMetric($method);

            if ($metric instanceof MethodMetric) {
                $methods[] = $metric;
            }
        }

        return $methods;
    }

    private function buildMetric(\SimpleXMLElement $method): ?MethodMetric
    {
        $name = (string) $method->methodName;

        if ($name === '' || (string) $method->crap === '') {
            return null;
        }

        $className = (string) $method->className;
        $complexity = (string) $method->complexity !== '' ? (int) $method->complexity : null;
        $coverage = (string) $method->coverage !== '' ? (float) $method->coverage : null;

        return new MethodMetric(
            name: $name,
            crap: (float) $method->crap,
            className: $className !== '' ? $className : null,
            file: null,
            line: null,
            complexity: $complexity,
            coverage: $coverage,
        );
    }
}
