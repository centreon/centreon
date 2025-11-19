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
use App\MonitoringConfiguration\Domain\Security\CommandActionEnum;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\DuplicateCommandInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Command\DuplicateCommandsProcessor;

#[Post(
    shortName: 'Command',
    uriTemplate: '/configuration/commands/_duplicate',
    processor: DuplicateCommandsProcessor::class,
    input: DuplicateCommandInput::class,
    openapi: new Model\Operation(
        responses: [
            403 => new Model\Response('You are not allowed to duplicate commands'),
        ],
    ),
    securityPostValidation: "is_granted('" . CommandActionEnum::Create->value . "', object)",
    securityPostValidationMessage: 'You are not allowed to duplicate commands',
)]
final readonly class DuplicateCommandResource
{
    public function __construct(
        #[ApiProperty(readableLink: false)]
        public readonly ?CommandResource $command,

        #[ApiProperty(
            description: 'The status of the duplication operation',
            openapiContext: ['example' => 204]
        )]
        public readonly int $status,

        #[ApiProperty(
            description: 'Descriptive message about the duplication result',
            openapiContext: ['example' => 'Command duplicated successfully']
        )]
        public readonly string $message,
    ) {
    }
}