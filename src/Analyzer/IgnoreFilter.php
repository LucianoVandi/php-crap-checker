<?php

declare(strict_types=1);

namespace Lvandi\PhpCrapChecker\Analyzer;

use Lvandi\PhpCrapChecker\ValueObject\MethodMetric;

final class IgnoreFilter
{
    /**
     * @param list<MethodMetric> $methods
     * @param list<string> $ignorePaths
     * @param list<string> $ignoreMethods
     * @return list<MethodMetric>
     */
    public function filter(array $methods, array $ignorePaths, array $ignoreMethods): array
    {
        if ($ignorePaths === [] && $ignoreMethods === []) {
            return $methods;
        }

        return array_values(array_filter(
            $methods,
            fn (MethodMetric $method): bool => !$this->isIgnored($method, $ignorePaths, $ignoreMethods),
        ));
    }

    /**
     * @param list<string> $ignorePaths
     * @param list<string> $ignoreMethods
     */
    private function isIgnored(MethodMetric $method, array $ignorePaths, array $ignoreMethods): bool
    {
        return $this->matchesPath($method, $ignorePaths) || $this->matchesMethod($method, $ignoreMethods);
    }

    /**
     * @param list<string> $ignorePaths
     */
    private function matchesPath(MethodMetric $method, array $ignorePaths): bool
    {
        if ($method->file === null) {
            return false;
        }

        foreach ($ignorePaths as $pattern) {
            if (fnmatch($pattern, $method->file)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $ignoreMethods
     */
    private function matchesMethod(MethodMetric $method, array $ignoreMethods): bool
    {
        $identifier = $method->className !== null
            ? $method->className . '::' . $method->name
            : $method->name;

        return in_array($identifier, $ignoreMethods, true);
    }
}
