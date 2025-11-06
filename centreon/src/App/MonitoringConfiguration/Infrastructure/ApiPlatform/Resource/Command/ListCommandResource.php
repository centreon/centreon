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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Command;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\MonitoringConfiguration\Domain\Repository\CommandResourceCount;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Command\ListCommandsProvider;

#[GetCollection(
    shortName: 'Command',
    uriTemplate: '/configuration/commands.{_format}',
    /* itemUriTemplate: '/configuration/commands/{id}.{_format}', */
    /* itemUriVariables: ['id' => new Link(fromClass: ListCommandResource::class, toClass: CommandResource::class, fromProperty: 'id', toProperty: 'id')], */
    provider: ListCommandsProvider::class,
    openapi: new Model\Operation(
        responses: [
            403 => new Model\Response('You are not allowed to access commands'),
        ],
        parameters: [
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
            new Model\Parameter(
                name: 'type[]',
                in: 'query',
                description: 'Filter by command type',
                required: false,
                schema: [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                        'enum' => [
                            CommandTypeEnum::Check->name,
                            CommandTypeEnum::Notification->name,
                            CommandTypeEnum::Miscellaneous->name,
                            CommandTypeEnum::Discovery->name,
                        ],
                    ],
                ],
            ),
            new Model\Parameter(
                name: 'is_activated',
                in: 'query',
                description: 'Filter by activation status',
                required: false,
                schema: [
                    'type' => 'boolean',
                ],
            ),
        ],
    ),
)]
final class ListCommandResource
{
    #[ApiProperty(
        description: 'Number of hosts using this command',
        openapiContext: ['example' => 10],
    )]
    public int $usedHostsCount;

    #[ApiProperty(
        description: 'Number of host templates using this command',
        openapiContext: ['example' => 100],
    )]
    public int $usedHostTemplatesCount;

    #[ApiProperty(
        description: 'Number of services using this command',
        openapiContext: ['example' => 5],
    )]
    public int $usedServicesCount;

    #[ApiProperty(
        description: 'Number of service templates using this command',
        openapiContext: ['example' => 50],
    )]
    public int $usedServiceTemplatesCount;

    public function __construct(
        #[ApiProperty(identifier: true, writable: false)]
        public int $id,

        #[ApiProperty(
            description: 'The command name',
            openapiContext: ['example' => 'check_http']
        )]
        public string $name,

        #[ApiProperty(
            description: 'The type of command (1: notification, 2: check, 3: Miscellaneous, 4: Discovery)',
            openapiContext: [
                'example' => 'Check',
                'enum' => [
                    CommandTypeEnum::Check->name,
                    CommandTypeEnum::Notification->name,
                    CommandTypeEnum::Miscellaneous->name,
                    CommandTypeEnum::Discovery->name,
                ],
            ],
        )]
        public string $type,

        #[ApiProperty(
            description: 'The command line used to execute the command',
            openapiContext: ['example' => '$USER1$/check_http -H $ARG1$ -w $ARG2$ -c $ARG3$']
        )]
        public string $commandLine,

        #[ApiProperty(
            description: 'Indicates whether the command is activated or not',
        )]
        public bool $isActivated,

    ) {
    }

    public function hydrateLinkedResourceCount(CommandResourceCount $commandResourceCount): void
    {
        $this->usedHostsCount = $commandResourceCount->usedHosts;
        $this->usedHostTemplatesCount = $commandResourceCount->usedHostTemplates;
        $this->usedServicesCount = $commandResourceCount->usedServices;
        $this->usedServiceTemplatesCount = $commandResourceCount->usedServiceTemplates;
    }
}
