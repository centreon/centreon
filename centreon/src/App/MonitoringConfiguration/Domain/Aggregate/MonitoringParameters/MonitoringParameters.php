<?php

namespace App\MonitoringConfiguration\Domain\Aggregate\MonitoringParameters;

final readonly class MonitoringParameters
{
    public function __construct(
        public MonitoringDefaultRefreshInterval $monitoringDefaultRefreshInterval,
        public StatisticsDefaultRefreshInterval $statisticsDefaultRefreshInterval,
        public MonitoringDefaultDowntimeDuration $monitoringDefaultDowntimeDuration,
        public bool $monitoringDefaultAcknowledgementSticky,
        public bool $monitoringDefaultAcknowledgementPersistent,
        public bool $monitoringDefaultAcknowledgementNotify,
        public bool $monitoringDefaultAcknowledgementWithServices,
        public bool $monitoringDefaultAcknowledgementForceActiveChecks,
        public bool $monitoringDefaultDowntimeFixed,
        public bool $monitoringDefaultDowntimeWithServices,
        public bool $isResourceStatusFullSearchEnabled,
    ) {
    }
}
