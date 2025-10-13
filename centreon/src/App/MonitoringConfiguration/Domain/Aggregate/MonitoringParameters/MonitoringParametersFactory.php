<?php

namespace App\MonitoringConfiguration\Domain\Aggregate\MonitoringParameters;

use App\MonitoringConfiguration\Domain\Aggregate\Option\Option;
use Webmozart\Assert\Assert;

final readonly class MonitoringParametersFactory
{
    /**
     * @param Option[] $options
     */
    public static function fromOptions(iterable $options): MonitoringParameters
    {
        Assert::allIsInstanceOf($options, Option::class);
        $optionsByName = [];
        foreach ($options as $option) {
            $optionsByName[$option->name->value] = $option;
        }
        return new MonitoringParameters(
            monitoringDefaultRefreshInterval: new MonitoringDefaultRefreshInterval(
                (int) ($optionsByName['AjaxTimeReloadMonitoring']->value->value ?? 60)
            ),
            statisticsDefaultRefreshInterval: new StatisticsDefaultRefreshInterval(
                (int) ($optionsByName['AjaxTimeReloadStatistic']->value->value ?? 300)
            ),
            monitoringDefaultDowntimeDuration: new MonitoringDefaultDowntimeDuration(
                self::convertDowntimeDurationToSeconds(
                    new MonitoringDefaultDowntimeDuration(
                        (int) ($optionsByName['monitoring_dwt_duration']->value->value ?? 60)
                    ),
                    new MonitoringDefaultDowntimeScale(
                        $optionsByName['monitoring_dwt_duration_scale']->value->value
                        ?? MonitoringDefaultDowntimeScale::DEFAULT_DOWNTIME_SCALE_MINUTE
                    )
                )
            ),
            monitoringDefaultAcknowledgementSticky:
                (bool) $optionsByName['monitoring_ack_sticky']->value->value ?? false,
            monitoringDefaultAcknowledgementPersistent:
                (bool) $optionsByName['monitoring_ack_persistent']->value->value ?? false,
            monitoringDefaultAcknowledgementNotify:
                (bool) $optionsByName['monitoring_ack_notify']->value->value ?? false,
            monitoringDefaultAcknowledgementWithServices:
                (bool) $optionsByName['monitoring_ack_svc']->value->value ?? false,
            monitoringDefaultAcknowledgementForceActiveChecks:
                (bool) $optionsByName['monitoring_ack_active_checks']->value->value ?? false,
            monitoringDefaultDowntimeFixed:
                (bool) $optionsByName['monitoring_dwt_fixed']->value->value ?? false,
            monitoringDefaultDowntimeWithServices:
                (bool) $optionsByName['monitoring_dwt_svc']->value->value ?? false,
            isResourceStatusFullSearchEnabled:
                (bool) $optionsByName['resource_status_search_mode']->value->value ?? false,
        );
    }

    public static function convertDowntimeDurationToSeconds(
        MonitoringDefaultDowntimeDuration $duration,
        MonitoringDefaultDowntimeScale $scale
    ): int {
        return match ($scale) {
            MonitoringDefaultDowntimeScale::DEFAULT_DOWNTIME_SCALE_MINUTE => $duration->value * 60,
            MonitoringDefaultDowntimeScale::DEFAULT_DOWNTIME_SCALE_HOUR => $duration->value * 3600,
            MonitoringDefaultDowntimeScale::DEFAULT_DOWNTIME_SCALE_DAY => $duration->value * 86400,
            default => $duration->value, // seconds
        };
    }
}
