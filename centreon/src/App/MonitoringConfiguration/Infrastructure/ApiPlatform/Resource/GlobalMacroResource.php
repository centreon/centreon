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
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model;
use App\MonitoringConfiguration\Domain\Aggregate\GlobalMacro\GlobalMacroName;
use App\MonitoringConfiguration\Domain\Security\GlobalMacroPermissionEnum;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\ListGlobalMacrosProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/configuration/global-macros',
            provider: ListGlobalMacrosProvider::class,
            openapi: new Model\Operation(
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
                ],
            ),
            security: "is_granted('" . GlobalMacroPermissionEnum::CanRead->value . "')",
            securityMessage: 'You are not allowed to list global macros',
        ),
    ],
)]
final class GlobalMacroResource
{
    public function __construct(
        #[ApiProperty(identifier: true, writable: false)]
        public ?int $id = null,

        #[Assert\NotNull]
        #[Assert\Length(min: 1, max: 255)]
        #[Assert\Regex(
            pattern: GlobalMacroName::NAMING_VALIDATION_REGEX,
            message: "The name must start and end with a '$'"
        )]
        #[ApiProperty(
            description: 'Name of the global macro',
            openapiContext: ['example' => '$USER1$']
        )]
        public ?string $name = null,

        #[Assert\NotNull]
        #[Assert\Length(min: 1, max: 255)]
        #[ApiProperty(
            description: 'Expression (value) of the global macro',
            openapiContext: ['example' => '/usr/lib64/nagios/plugins']
        )]
        public ?string $expression = null,

        #[Assert\Length(max: 255)]
        #[ApiProperty(
            description: 'Additional information about the macro',
            openapiContext: ['example' => 'This macro is used to define the path of plugins']
        )]
        public ?string $comment = null,

        public bool $isPassword = false,
        public bool $isActivated = false,
    ) {
    }
}
