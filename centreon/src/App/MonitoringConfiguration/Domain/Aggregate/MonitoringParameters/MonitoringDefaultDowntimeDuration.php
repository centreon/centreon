<?php

namespace App\MonitoringConfiguration\Domain\Aggregate\MonitoringParameters;

use Webmozart\Assert\Assert;

final readonly class MonitoringDefaultDowntimeDuration
{
    public function __construct(public int $value)
    {
        Assert::greaterThan($value, 300);
    }
}
