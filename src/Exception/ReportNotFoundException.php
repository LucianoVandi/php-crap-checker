<?php

declare(strict_types=1);

namespace Lvandi\PhpCrapChecker\Exception;

final class ReportNotFoundException extends \RuntimeException
{
    public static function forPath(string $path): self
    {
        return new self(sprintf('Report not found: %s', $path));
    }
}
