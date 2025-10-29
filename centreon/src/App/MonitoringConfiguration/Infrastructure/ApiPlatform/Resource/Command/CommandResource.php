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
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\MonitoringConfiguration\Domain\Security\CommandPermissionEnum;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Input\CreateCommandInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Command\FindCommandProvider;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Command\CreateCommandProcessor;

#[ApiResource(
    shortName: 'Command',
    operations: [
        new Get(
            uriTemplate: '/configuration/commands/{id}',
            provider: FindCommandProvider::class,
            openapi: new Model\Operation(
                responses: [
                    404 => new Model\Response('Command resource not found'),
                    403 => new Model\Response('You are not allowed to access this command'),
                ],
            ),
            security: '
                (object.type == "' . CommandTypeEnum::Notification->name . '" and (
                    is_granted("' . CommandPermissionEnum::CanReadNotifications->value . '") or
                    is_granted("' . CommandPermissionEnum::CanReadAndWriteNotifications->value . '")
                )) or
                (object.type == "' . CommandTypeEnum::Check->name . '" and (
                    is_granted("' . CommandPermissionEnum::CanReadChecks->value . '") or
                    is_granted("' . CommandPermissionEnum::CanReadAndWriteChecks->value . '")
                )) or
                (object.type == "' . CommandTypeEnum::Miscellaneous->name . '" and (
                    is_granted("' . CommandPermissionEnum::CanReadMiscellaneous->value . '") or
                    is_granted("' . CommandPermissionEnum::CanReadAndWriteMiscellaneous->value . '")
                )) or
                (object.type == "' . CommandTypeEnum::Discovery->name . '" and (
                    is_granted("' . CommandPermissionEnum::CanReadDiscovery->value . '") or
                    is_granted("' . CommandPermissionEnum::CanReadAndWriteDiscovery->value . '")
                ))
            ',
            securityMessage: 'You are not allowed to access this command',
        ),
        new Post(
            uriTemplate: '/configuration/commands',
            input: CreateCommandInput::class,
            processor: CreateCommandProcessor::class,
            openapi: new Model\Operation(
                responses: [
                    409 => new Model\Response('Command resource already exists'),
                ],
            ),
            securityPostDenormalize: '
                object.type == "' . CommandTypeEnum::Notification->name
                . '" and is_granted("' . CommandPermissionEnum::CanReadAndWriteNotifications->value . '")
                or
                object.type == "' . CommandTypeEnum::Check->name
                . '" and is_granted("' . CommandPermissionEnum::CanReadAndWriteChecks->value . '")
                or
                object.type == "' . CommandTypeEnum::Miscellaneous->name
                . '" and is_granted("' . CommandPermissionEnum::CanReadAndWriteMiscellaneous->value . '")
                or
                object.type == "' . CommandTypeEnum::Discovery->name
                . '" and is_granted("' . CommandPermissionEnum::CanReadAndWriteDiscovery->value . '")
            ',
            securityMessage: 'You are not allowed to create commands',
        )
    ],
)]
final class CommandResource
{
    public function __construct(
        #[ApiProperty(identifier: true, writable: false)]
        public ?int $id,

        #[ApiProperty(
            description: 'The command name',
            openapiContext: ['example' => 'check_http']
        )]
        public ?string $name,

        #[ApiProperty(
            description: 'The type of command (1: notification, 2: check, 3: Miscellaneous, 4: Discovery)',
            openapiContext: [
                'example' => 'Check',
                'enum' => [
                    CommandTypeEnum::Check->name,
                    CommandTypeEnum::Notification->name,
                    CommandTypeEnum::Miscellaneous->name,
                    CommandTypeEnum::Discovery->name
                ]
            ],
        )]
        public string $type,

        #[ApiProperty(
            description: 'The command line used to execute the command',
            openapiContext: ['example' => '$USER1$/check_http -H $ARG1$ -w $ARG2$ -c $ARG3$']
        )]
        public string $commandLine,

        #[ApiProperty(
            description: 'Indicates whether the command can be executed through a shell',
        )]
        public bool $isShellEnabled,

        #[ApiProperty(
            description: 'Indicates whether the command is activated or not',
        )]
        public bool $isActivated,

        #[ApiProperty(
            description: 'Indicates whether the command comes from a monitoring connector',
        )]
        public bool $isFromMonitoringConnector,

        #[ApiProperty(
            description: 'Connectors are run in background and execute specific commands without the need to execute a binary, thus enhancing performance',
            openapiContext: [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                ],
                'example' => ['id' => 1, 'name' => 'SSH Connector'],
            ]
        )]
        public ?ConnectorDto $connector = null,

        #[ApiProperty(
            description: 'Additional information about the command',
            openapiContext: ['example' => 'This command is used to check the HTTP service']
        )]
        public ?string $comment = null,
    ) {
    }
}
