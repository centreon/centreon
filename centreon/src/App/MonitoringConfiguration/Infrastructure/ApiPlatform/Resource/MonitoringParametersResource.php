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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\GetMonitoringParametersProvider;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/monitoring/parameters',
            provider: GetMonitoringParametersProvider::class,
        ),
    ],
)]
final class MonitoringParametersResource
{
    public function __construct(
        #[ApiProperty(
            description: 'Name of the global macro',
            openapiContext: ['example' => '$USER1$']
        )]
        public ?int $monitoringDefaultRefreshInterval = null,

        #[ApiProperty(
            description: 'Expression (value) of the global macro',
            openapiContext: ['example' => '/usr/lib64/nagios/plugins']
        )]
        public ?int $statisticsDefaultRefreshInterval = null,

        public ?int $monitoringDefaultDowntimeDuration = null,

        #[ApiProperty(
            description: 'Name of the global macro',
            openapiContext: ['example' => '$USER1$']
        )]
        public ?bool $monitoringDefaultAcknowledgementSticky = null,

        #[ApiProperty(
            description: 'Expression (value) of the global macro',
            openapiContext: ['example' => '/usr/lib64/nagios/plugins']
        )]
        public ?bool $monitoringDefaultAcknowledgementPersistent = null,

        #[ApiProperty(
            description: 'Name of the global macro',
            openapiContext: ['example' => '$USER1$']
        )]
        public ?bool $monitoringDefaultAcknowledgementNotify = null,

        #[ApiProperty(
            description: 'Expression (value) of the global macro',
            openapiContext: ['example' => '/usr/lib64/nagios/plugins']
        )]
        public ?bool $monitoringDefaultAcknowledgementWithServices = null,

        #[ApiProperty(
            description: 'Name of the global macro',
            openapiContext: ['example' => '$USER1$']
        )]
        public ?bool $monitoringDefaultAcknowledgementForceActiveChecks = null,

        #[ApiProperty(
            description: 'Expression (value) of the global macro',
            openapiContext: ['example' => '/usr/lib64/nagios/plugins']
        )]
        public ?bool $monitoringDefaultDowntimeFixed = null,

        #[ApiProperty(
            description: 'Expression (value) of the global macro',
            openapiContext: ['example' => '/usr/lib64/nagios/plugins']
        )]
        public ?bool $monitoringDefaultDowntimeWithServices = null,

        #[ApiProperty(
            description: 'Name of the global macro',
            openapiContext: ['example' => '$USER1$']
        )]
        public ?bool $isResourceStatusFullSearchEnabled = null,

    ) {
    }
}
