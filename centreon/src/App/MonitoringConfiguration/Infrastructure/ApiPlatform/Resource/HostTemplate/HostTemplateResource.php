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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model;
use App\MonitoringConfiguration\Domain\Security\HostTemplatePermissionEnum;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\HostTemplate\ListHostTemplatesProvider;

#[ApiResource(
    shortName: 'HostTemplate',
    operations: [
        new GetCollection(
            uriTemplate: '/configuration/host_templates',
            provider: ListHostTemplatesProvider::class,
            output: HostTemplateCollectionOutput::class,
            openapi: new Model\Operation(
                parameters: [
                    new Model\Parameter(
                        name: 'name[lk]',
                        in: 'query',
                        description: 'Filter by host template name using "like" operator',
                        required: false,
                        schema: ['type' => 'string'],
                    ),
                ],
            ),
            security: '
                is_granted("' . HostTemplatePermissionEnum::CanRead->value . '") or
                is_granted("' . HostTemplatePermissionEnum::CanReadAndWrite->value . '")',
            securityMessage: 'You are not allowed to list host templates',
        ),
    ],
)]
final class HostTemplateResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public int $id,

        public string $name,
    ) {
    }
}
