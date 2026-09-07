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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostGroup;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model;
use App\MonitoringConfiguration\Domain\Security\HostGroupPermissionEnum;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\HostGroup\ListHostGroupsProvider;

#[ApiResource(
    shortName: 'HostGroup',
    operations: [
        new GetCollection(
            uriTemplate: '/configuration/host_groups',
            provider: ListHostGroupsProvider::class,
            output: HostGroupCollectionOutput::class,
            openapi: new Model\Operation(
                parameters: [
                    new Model\Parameter(
                        name: 'name[lk]',
                        in: 'query',
                        description: 'Filter by host group name using "like" operator',
                        required: false,
                        schema: ['type' => 'string'],
                    ),
                ],
            ),
            security: '
                is_granted("' . HostGroupPermissionEnum::CanRead->value . '") or
                is_granted("' . HostGroupPermissionEnum::CanReadAndWrite->value . '")',
            securityMessage: 'You are not allowed to list host groups',
        ),
    ],
)]
final class HostGroupResource
{
    public function __construct(
        #[ApiProperty(identifier: true, writable: false)]
        public int $id,

        public string $name,
    ) {
    }
}
