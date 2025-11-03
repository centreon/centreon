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

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\FindConnectorProvider;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\ListConnectorsProvider;
use App\Shared\Domain\Collection;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/configuration/connectors',
            provider: ListConnectorsProvider::class,
            openapi: new Model\Operation(
                parameters: [
                    new Model\Parameter(
                        name: 'id[lk]',
                        in: 'query',
                        description: 'Filter by id using "like" operator',
                        required: false,
                        schema: [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                    ),
                    new Model\Parameter(
                        name: 'id[eq]',
                        in: 'query',
                        description: 'Filter by id using "equals" operator',
                        required: false,
                        schema: [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                    ),
                    new Model\Parameter(
                        name: 'name[lk]',
                        in: 'query',
                        description: 'Filter by name using "like" operator',
                        required: false,
                        schema: [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                    ),
                    new Model\Parameter(
                        name: 'name[eq]',
                        in: 'query',
                        description: 'Filter by name using "equals" operator',
                        required: false,
                        schema: [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                    ),
                ],
            ),
            /* security: "is_granted('" . ConnectorPermissionEnum::CanRead->value . "')", */
            securityMessage: 'You are not allowed to list global macros',
        ),
        new Get(
            shortName: 'Connector',
            uriTemplate: '/configuration/connectors/{id}',
            provider: FindConnectorProvider::class,
        )
    ],
)]
#[Get(
    shortName: 'Connector',
    uriTemplate: '/configuration/connectors/{id}',
    provider: FindConnectorProvider::class,
)]
final class ConnectorResource
{
    public function __construct(
        public int $id,
        public string $name,
        public string $commandLine,
        public ?string $description,
        public Collection $commands,
        public bool $isActivated,
    ) {
    }
}
