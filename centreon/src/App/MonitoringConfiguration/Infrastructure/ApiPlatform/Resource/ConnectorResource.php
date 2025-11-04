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
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model;
use App\MonitoringConfiguration\Domain\Security\ConnectorPermissionEnum;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\FindConnectorProvider;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\ListConnectorsProvider;
use Symfony\Component\Serializer\Annotation\Groups;

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
                            'items' => ['type' => 'int'],
                        ],
                    ),
                    new Model\Parameter(
                        name: 'id[eq]',
                        in: 'query',
                        description: 'Filter by id using "equals" operator',
                        required: false,
                        schema: [
                            'type' => 'array',
                            'items' => ['type' => 'int'],
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
            security: '
                is_granted("' . ConnectorPermissionEnum::CanRead->value . '") or
                is_granted("' . ConnectorPermissionEnum::CanReadAndWrite->value . '")',
            securityMessage: 'You are not allowed to list connectors',
        ),
        new Get(
            shortName: 'Connector',
            uriTemplate: '/configuration/connectors/{id}',
            provider: FindConnectorProvider::class,
            security: '
                is_granted("' . ConnectorPermissionEnum::CanRead->value . '") or
                is_granted("' . ConnectorPermissionEnum::CanReadAndWrite->value . '")',
            securityMessage: 'You are not allowed to list connectors',
        ),
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
        #[ApiProperty(identifier: true, writable: false)]
        #[Groups(['default'])]
        public int $id,

        #[ApiProperty(
            description: 'The connector name',
            openapiContext: ['example' => 'Perl Connector']
        )]
        #[Groups(['default'])]
        public string $name,

        #[ApiProperty(
            description: 'The command line used to execute the connector',
            openapiContext: ['example' => 'centreon_connector_ssh --log-file=/var/log/centreon-engine/connector-ssh.log']
        )]
        #[Groups(['default'])]
        public string $commandLine,

        #[ApiProperty(
            description: 'The connector description',
            openapiContext: ['example' => 'Connector using SSH to connect to remote hosts']
        )]
        #[Groups(['default'])]
        public ?string $description,

        #[ApiProperty(
            description: 'Indicates whether the connector is activated',
            openapiContext: ['example' => true]
        )]
        #[Groups(['default'])]
        public bool $isActivated,
    ) {
    }
}
