<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

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
                isset($optionsByName['AjaxTimeReloadMonitoring'])
                    ? (int) $optionsByName['AjaxTimeReloadMonitoring']->value->value
                    : 15
            ),
            statisticsDefaultRefreshInterval: new StatisticsDefaultRefreshInterval(
                isset($optionsByName['AjaxTimeReloadStatistic'])
                    ? (int) $optionsByName['AjaxTimeReloadStatistic']->value->value
                    : 15
            ),
            monitoringDefaultDowntimeDuration: new MonitoringDefaultDowntimeDuration(
                self::convertDowntimeDurationToSeconds(
                    new MonitoringDefaultDowntimeDuration(
                        isset($optionsByName['monitoring_dwt_duration'])
                            ? (int) $optionsByName['monitoring_dwt_duration']->value->value
                            : 3600
                    ),
                    new MonitoringDefaultDowntimeScale(
                        isset($optionsByName['monitoring_dwt_duration_scale'])
                            ? $optionsByName['monitoring_dwt_duration_scale']->value->value
                            : MonitoringDefaultDowntimeScale::DEFAULT_DOWNTIME_SCALE_MINUTE
                    )
                )
            ),
            monitoringDefaultAcknowledgementSticky: isset($optionsByName['monitoring_ack_sticky'])
                ? (bool) $optionsByName['monitoring_ack_sticky']->value->value
                : false,
            monitoringDefaultAcknowledgementPersistent: isset($optionsByName['monitoring_ack_persistent'])
                ? (bool) $optionsByName['monitoring_ack_persistent']->value->value
                : false,
            monitoringDefaultAcknowledgementNotify: isset($optionsByName['monitoring_ack_notify'])
                ? (bool) $optionsByName['monitoring_ack_notify']->value->value
                : false,
            monitoringDefaultAcknowledgementWithServices: isset($optionsByName['monitoring_ack_svc'])
                ? (bool) $optionsByName['monitoring_ack_svc']->value->value
                : false,
            monitoringDefaultAcknowledgementForceActiveChecks: isset($optionsByName['monitoring_ack_active_checks'])
                ? (bool) $optionsByName['monitoring_ack_active_checks']->value->value
                : false,
            monitoringDefaultDowntimeFixed: isset($optionsByName['monitoring_dwt_fixed'])
                ? (bool) $optionsByName['monitoring_dwt_fixed']->value->value
                : false,
            monitoringDefaultDowntimeWithServices: isset($optionsByName['monitoring_dwt_svc'])
                ? (bool) $optionsByName['monitoring_dwt_svc']->value->value
                : false,
            isResourceStatusFullSearchEnabled: isset($optionsByName['resource_status_search_mode'])
                ? (bool) $optionsByName['resource_status_search_mode']->value->value
                : false,
        );
    }

    public static function convertDowntimeDurationToSeconds(
        MonitoringDefaultDowntimeDuration $duration,
        MonitoringDefaultDowntimeScale $scale,
    ): int {
        return match ($scale->value) {
            MonitoringDefaultDowntimeScale::DEFAULT_DOWNTIME_SCALE_MINUTE => $duration->value * 60,
            MonitoringDefaultDowntimeScale::DEFAULT_DOWNTIME_SCALE_HOUR => $duration->value * 3600,
            MonitoringDefaultDowntimeScale::DEFAULT_DOWNTIME_SCALE_DAY => $duration->value * 86400,
            default => $duration->value, // seconds
        };
    }
}
