<?php

declare(strict_types=1);

namespace Lvandi\PhpCrapChecker\Exception;

final class InvalidReportException extends \RuntimeException
{
    public static function malformedXml(string $path): self
    {
        return new self(sprintf('Invalid XML in report: %s', $path));
    }

    public static function missingCrapValue(string $methodName): self
    {
        return new self(sprintf('Missing CRAP value for method: %s', $methodName));
    }
}
