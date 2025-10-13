<?php

namespace App\MonitoringConfiguration\Domain\Aggregate\MonitoringParameters;

use Webmozart\Assert\Assert;

final readonly class MonitoringDefaultRefreshInterval
{
    public function __construct(public int $value)
    {
        Assert::greaterThanEq($value, 10);
        Assert::lessThanEq($value, 3600);
    }
}
