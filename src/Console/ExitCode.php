<?php

declare(strict_types=1);

namespace Lvandi\PhpCrapChecker\Console;

enum ExitCode: int
{
    case Success = 0;
    case ThresholdExceeded = 1;
    case InvalidInput = 2;
    case ReportNotFound = 3;
    case InvalidXml = 4;
    case NoMethodsFound = 5;
}
