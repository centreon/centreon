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

declare(strict_types=1);

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State;

use App\MonitoringConfiguration\Domain\Aggregate\MonitoringParameters\MonitoringParameters;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\MonitoringParametersResource;
use App\Shared\Infrastructure\TransformerInterface;

/**
 * @implements TransformerInterface<MonitoringParameters,MonitoringParametersResource>
 */
final readonly class ResourceMonitoringParametersTransformer implements TransformerInterface
{
    public function transform(mixed $from): MonitoringParametersResource
    {
        return new MonitoringParametersResource(
            monitoringDefaultRefreshInterval: $from->monitoringDefaultRefreshInterval->value,
            statisticsDefaultRefreshInterval: $from->statisticsDefaultRefreshInterval->value,
            monitoringDefaultDowntimeDuration: $from->monitoringDefaultDowntimeDuration->value,
            monitoringDefaultAcknowledgementSticky: $from->monitoringDefaultAcknowledgementSticky,
            monitoringDefaultAcknowledgementPersistent: $from->monitoringDefaultAcknowledgementPersistent,
            monitoringDefaultAcknowledgementNotify: $from->monitoringDefaultAcknowledgementNotify,
            monitoringDefaultAcknowledgementWithServices: $from->monitoringDefaultAcknowledgementWithServices,
            monitoringDefaultAcknowledgementForceActiveChecks: $from->monitoringDefaultAcknowledgementForceActiveChecks,
            monitoringDefaultDowntimeFixed: $from->monitoringDefaultDowntimeFixed,
            monitoringDefaultDowntimeWithServices: $from->monitoringDefaultDowntimeWithServices,
            isResourceStatusFullSearchEnabled: $from->isResourceStatusFullSearchEnabled,
        );
    }
}
