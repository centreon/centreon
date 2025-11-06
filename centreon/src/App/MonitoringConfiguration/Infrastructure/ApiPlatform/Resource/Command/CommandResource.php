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
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandName;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\MonitoringConfiguration\Domain\Security\CommandActionEnum;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\ConnectorResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Command\FindCommandProvider;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Command\ListCommandsProvider;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Command\UpdateCommandProcessor;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'Command',
    operations: [
        new Get(
            uriTemplate: '/configuration/commands/{id}.{_format}',
            provider: FindCommandProvider::class,
            openapi: new Model\Operation(
                responses: [
                    404 => new Model\Response('Command resource not found'),
                    403 => new Model\Response('You are not allowed to access this command'),
                ],
            ),
            normalizationContext: ['groups' => ['default']],
            securityPostValidation: "is_granted('" . CommandActionEnum::Read->value . "', object)",
            securityPostValidationMessage: 'You are not allowed to access this command',
        ),
        new Patch(
            uriTemplate: '/configuration/commands/{id}',
            provider: FindCommandProvider::class,
            processor: UpdateCommandProcessor::class,
            openapi: new Model\Operation(
                responses: [
                    404 => new Model\Response('Command resource not found'),
                    403 => new Model\Response('You are not allowed to update this command'),
                ],
            ),
            normalizationContext: ['groups' => ['default']],
            securityPostValidation: "is_granted('" . CommandActionEnum::Update->value . "', object)",
            securityPostValidationMessage: 'You are not allowed to update this command',
        ),
    ],
)]
final class CommandResource
{
    public function __construct(
        #[ApiProperty(identifier: true, writable: false)]
        #[Groups(['default'])]
        public int $id,

        #[ApiProperty(
            description: 'The command name',
            openapiContext: ['example' => 'check_http']
        )]
        #[Assert\Length(min: 1, max: 255)]
        #[Assert\Regex(
            pattern: CommandName::NAME_VALIDATION_REGEX,
            message: 'The name can only contain alphanumeric characters, underscores, and hyphens.'
        )]
        #[Groups(['default'])]
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
        #[Assert\Choice(
            choices: [
                CommandTypeEnum::Check->name,
                CommandTypeEnum::Notification->name,
                CommandTypeEnum::Miscellaneous->name,
                CommandTypeEnum::Discovery->name,
            ]
        )]
        #[Groups(['default'])]
        public string $type,

        #[ApiProperty(
            description: 'The command line used to execute the command',
            openapiContext: ['example' => '$USER1$/check_http -H $ARG1$ -w $ARG2$ -c $ARG3$']
        )]
        #[Assert\Length(min: 1, max: 65535)]
        #[Groups(['default'])]
        public string $commandLine,

        #[ApiProperty(
            description: 'Indicates whether the command can be executed through a shell',
        )]
        #[Groups(['default'])]
        public bool $isShellEnabled,

        #[ApiProperty(
            description: 'Indicates whether the command is activated or not',
        )]
        #[Groups(['default'])]
        public bool $isActivated,

        #[ApiProperty(
            description: 'Indicates whether the command comes from a monitoring connector',
        )]
        #[Groups(['default'])]
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
            ],
            readableLink: true,
        )]
        #[Groups(['default'])]
        public ?ConnectorResource $connector,

        #[ApiProperty(
            description: 'Additional information about the command',
            openapiContext: ['example' => 'This command is used to check the HTTP service']
        )]
        #[Groups(['default'])]
        public ?string $comment,
    ) {
    }
}
