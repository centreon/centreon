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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\NotExposed;
use ApiPlatform\OpenApi\Model;
use App\MonitoringConfiguration\Domain\Security\ServicePermissionEnum;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service\ListServicesProvider;

#[ApiResource(
    shortName: 'Service',
    operations: [
        new GetCollection(
            uriTemplate: '/configuration/services',
            itemUriTemplate: '/configuration/services/{id}',
            openapi: new Model\Operation(
                summary: 'List the services',
                description: 'Services linked to host groups are not listed. The check intervals and the icon are the '
                    . 'effective ones, inherited from the service templates when the service has none of its own; '
                    . 'intervals are in engine units.',
                parameters: [
                    new Model\Parameter(
                        name: 'host_name[lk]',
                        in: 'query',
                        description: 'Filter by host name using "like" operator',
                        required: false,
                        schema: ['type' => 'string'],
                    ),
                    new Model\Parameter(
                        name: 'name[lk]',
                        in: 'query',
                        description: 'Filter by service name using "like" operator',
                        required: false,
                        schema: ['type' => 'string'],
                    ),
                    new Model\Parameter(
                        name: 'template_id',
                        in: 'query',
                        description: 'Filter by direct service template id',
                        required: false,
                        schema: ['type' => 'integer'],
                    ),
                    new Model\Parameter(
                        name: 'activated',
                        in: 'query',
                        description: 'Filter by service activation status',
                        required: false,
                        schema: ['type' => 'boolean'],
                    ),
                    new Model\Parameter(
                        name: 'host_activated',
                        in: 'query',
                        description: 'Filter by host activation status; all hosts when absent',
                        required: false,
                        schema: ['type' => 'boolean'],
                    ),
                    new Model\Parameter(
                        name: 'sort',
                        in: 'query',
                        description: 'Sort by one or several fields, in order of precedence (e.g. `sort[host_name]=asc`). '
                            . 'Default: host name, then service name.',
                        required: false,
                        style: 'deepObject',
                        explode: true,
                        schema: [
                            'type' => 'object',
                            'properties' => [
                                'host_name' => ['type' => 'string', 'enum' => ['asc', 'desc']],
                                'name' => ['type' => 'string', 'enum' => ['asc', 'desc']],
                            ],
                        ],
                    ),
                ],
            ),
            security: '
                is_granted("' . ServicePermissionEnum::CanRead->value . '") or
                is_granted("' . ServicePermissionEnum::CanReadAndWrite->value . '")',
            securityMessage: 'You are not allowed to list services',
            output: ServiceCollectionOutput::class,
            provider: ListServicesProvider::class,
        ),
        // Gives the listed services their IRI until a real item operation exists. Positive integers only, so the
        // other routes under /configuration/services keep matching their own operations.
        new NotExposed(
            uriTemplate: '/configuration/services/{id}',
            requirements: ['id' => '[1-9]\d*'],
        ),
    ],
)]
final class ServiceResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public int $id,
    ) {
    }
}
