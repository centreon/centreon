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
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandName;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\MonitoringConfiguration\Domain\Security\CommandActionEnum;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\ConnectorResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Command\CreateCommandProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[Post(
    uriTemplate: '/configuration/commands',
    processor: CreateCommandProcessor::class,
    openapi: new Model\Operation(
        responses: [
            409 => new Model\Response('Command resource already exists'),
            403 => new Model\Response('You are not allowed to create commands'),
        ],
    ),
    securityPostValidation: "is_granted('" . CommandActionEnum::Create->value . "', object)",
    securityPostValidationMessage: 'You are not allowed to create commands',
)]
final class CreateCommandResource
{
    public function __construct(
        #[ApiProperty(
            description: 'The command name',
            openapiContext: ['example' => 'check_http']
        )]
        #[Assert\Length(min: 1, max: 255)]
        #[Assert\Regex(
            pattern: CommandName::NAME_VALIDATION_REGEX,
            message: 'The name can only contain alphanumeric characters, underscores, and hyphens.'
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
        #[Assert\Choice(
            choices: [
                CommandTypeEnum::Check->name,
                CommandTypeEnum::Notification->name,
                CommandTypeEnum::Miscellaneous->name,
                CommandTypeEnum::Discovery->name,
            ]
        )]
        public string $type,

        #[ApiProperty(
            description: 'The command line used to execute the command',
            openapiContext: ['example' => '$USER1$/check_http -H $ARG1$ -w $ARG2$ -c $ARG3$']
        )]
        #[Assert\Length(min: 1, max: 65535)]
        public string $commandLine,

        #[ApiProperty(
            description: 'Indicates whether the command can be executed through a shell',
        )]
        public bool $isShellEnabled,

        public ?ConnectorResource $connector = null,

        #[ApiProperty(
            description: 'Additional information about the command',
            openapiContext: ['example' => 'This command is used to check the HTTP service']
        )]
        public ?string $comment = null,
    ) {
    }
}
