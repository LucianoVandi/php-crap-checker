<?php

declare(strict_types=1);

namespace Lvandi\PhpCrapChecker\Exception;

final class InvalidConfigException extends \RuntimeException
{
    public static function malformedYaml(string $path, string $reason): self
    {
        return new self(sprintf('Config file "%s" contains invalid YAML: %s', $path, $reason));
    }

    public static function invalidField(string $path, string $field, string $expected): self
    {
        return new self(sprintf('Config file "%s": field "%s" must be %s.', $path, $field, $expected));
    }

    public static function tooLarge(string $path, int $maxBytes): self
    {
        return new self(sprintf('Config file "%s" exceeds the maximum allowed size of %d bytes.', $path, $maxBytes));
    }
}
