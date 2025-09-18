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
use ApiPlatform\OpenApi\Model;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandArgument;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandMacro;
use App\MonitoringConfiguration\Domain\Security\CommandPermissionEnum;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Command\FindCommandProvider;
use App\Shared\Domain\Collection;

#[ApiResource(
    shortName: 'Command',
    operations: [
        new Get(
            uriTemplate: '/configuration/commands/{id}',
            provider: FindCommandProvider::class,
            openapi: new Model\Operation(
                responses: [
                    404 => new Model\Response('Command resource not found'),
                ],
            ),
            // security: "is_granted('" . CommandPermissionEnum::CanReadChecks->value . "')", // handle the other cases in the provider
            securityMessage: 'You are not allowed to access commands',
        ),
    ],
)]
final class CommandResource
{
    /**
     * @param Collection<CommandArgument> $arguments
     * @param Collection<CommandMacro> $macros
     */
    public function __construct(
        #[ApiProperty(identifier: true, writable: false)]
        public int $id,

        #[ApiProperty(
            description: 'The name of the command',
            openapiContext: ['example' => 'check_http']
        )]
        public string $name,

        #[ApiProperty(
            description: 'The type of command (1: notification, 2: check, 3: Miscellaneous, 4: Discovery)',
            openapiContext: ['example' => 1, 'enum' => [1, 2, 3, 4]]
        )]
        public int $type,

        #[ApiProperty(
            description: 'Indicates whether the command can be executed through a shell',
            openapiContext: ['example' => 0, 'enum' => [0, 1]]
        )]
        public bool $isShellEnabled,

        #[ApiProperty(
            description: 'Indicates whether the command is activated or not',
            openapiContext: ['example' => 1, 'enum' => [0, 1]]
        )]
        public bool $isActivated,

        #[ApiProperty(
            description: 'Indicates whether the command is locked or not',
            openapiContext: ['example' => 0, 'enum' => [0, 1]]
        )]
        public bool $isLocked,

        #[ApiProperty(
            description: 'The list of command macros related to the command',
            openapiContext: [
                'type' => 'array',
                'items' => ['id' => 'int', 'name' => 'string'], // check in docs/code
                'example' => ['ARG1', 'ARG2', 'ARG3'], // check in docs/code
            ]
        )]
        public Collection $macros,

        #[ApiProperty(
            description: 'The list of arguments related to the command',
            openapiContext: [
                'type' => 'array',
                'items' => ['id' => 'int', 'name' => 'string'], // check in docs/code
                'example' => [['id' => 1, 'name' => 'ARG1', 'value' => 'localhost']], // check in docs/code
            ]
        )]
        public Collection $arguments,

        #[ApiProperty(
            description: 'The command line used to execute the command',
            openapiContext: ['example' => '$USER1$/check_http -H $ARG1$ -w $ARG2$ -c $ARG3$'] // check docs for more examples
        )]
        public ?string $commandLine = null,

        #[ApiProperty(
            description: 'Example of arguments that can be passed to the command',
            openapiContext: ['example' => 'ARG1: host to check, ARG2: warning threshold, ARG3: critical threshold'] // check in docs/code
        )]
        public ?string $argumentExample = null,

        #[ApiProperty(
            description: 'The connector used to separate arguments in the command line',
            openapiContext: ['example' => null]
        )]
        public ?CommandConnectorDto $connector = null,

        #[ApiProperty(
            description: 'The graph template used for the command',
            openapiContext: ['example' => null]
        )]
        public ?CommandGraphTemplateDto $graphTemplate = null,

        #[ApiProperty(
            description: 'Additional information about the command',
            openapiContext: ['example' => 'This command is used to check the HTTP service']
        )]
        public ?string $comment = null,
    ) {
    }
}
