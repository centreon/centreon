<?php

namespace App\MonitoringConfiguration\Domain\Aggregate\MonitoringParameters;

use Webmozart\Assert\Assert;

final readonly class MonitoringDefaultDowntimeScale
{
    public const DEFAULT_DOWNTIME_SCALE_DAY = 'd';
    public const DEFAULT_DOWNTIME_SCALE_HOUR = 'h';
    public const DEFAULT_DOWNTIME_SCALE_MINUTE = 'm';
    public const DEFAULT_DOWNTIME_SCALE_SECOND = 's';
    public const ALLOWED_SCALES = [
        self::DEFAULT_DOWNTIME_SCALE_HOUR,
        self::DEFAULT_DOWNTIME_SCALE_MINUTE,
        self::DEFAULT_DOWNTIME_SCALE_SECOND,
    ];

    public function __construct(public string $value)
    {
        Assert::inArray($value, self::ALLOWED_SCALES);
    }
}
