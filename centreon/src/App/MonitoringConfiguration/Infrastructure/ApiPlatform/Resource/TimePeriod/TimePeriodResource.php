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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\TimePeriod;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\NotExposed;
use ApiPlatform\OpenApi\Model;
use App\MonitoringConfiguration\Domain\Security\HostPermissionEnum;
use App\MonitoringConfiguration\Domain\Security\TimePeriodPermissionEnum;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\TimePeriod\ListTimePeriodsChoicesProvider;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\TimePeriod\ListTimePeriodsCollectionProvider;

#[ApiResource(
    shortName: 'TimePeriod',
    operations: [
        new GetCollection(
            uriTemplate: '/configuration/hosts/timeperiods',
            openapi: false,
            security: '
                is_granted("' . HostPermissionEnum::CanReadAndWrite->value . '")',
            securityMessage: 'You are not allowed to access time periods',
            itemUriTemplate: '/configuration/timeperiods/{id}',
            output: TimePeriodChoicesOutput::class,
            provider: ListTimePeriodsChoicesProvider::class,
        ),
        new GetCollection(
            uriTemplate: '/configuration/timeperiods',
            openapi: new Model\Operation(
                parameters: [
                    new Model\Parameter(
                        name: 'name[lk]',
                        in: 'query',
                        description: 'Filter by time period name using a partial, case-insensitive match',
                        required: false,
                        schema: ['type' => 'string'],
                    ),
                ],
            ),
            security: '
                is_granted("' . TimePeriodPermissionEnum::CanRead->value . '") or
                is_granted("' . TimePeriodPermissionEnum::CanReadAndWrite->value . '")',
            // legacy TimePeriodException::accessNotAllowed() message, kept verbatim
            securityMessage: 'You are not allowed to access time periods',
            itemUriTemplate: '/configuration/timeperiods/{id}',
            output: TimePeriodCollectionOutput::class,
            provider: ListTimePeriodsCollectionProvider::class,
        ),
        // temporary, to make itemUriTemplate work
        new NotExposed(uriTemplate: '/configuration/timeperiods/{id}'),
    ],
)]
final class TimePeriodResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public int $id,

        public string $name,
    ) {
    }
}
