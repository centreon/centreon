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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\ContactGroup;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model;
use App\MonitoringConfiguration\Domain\Security\ContactGroupPermissionEnum;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\ContactGroup\ListContactGroupsProvider;

#[ApiResource(
    shortName: 'ContactGroup',
    operations: [
        new GetCollection(
            uriTemplate: '/configuration/contact_groups',
            provider: ListContactGroupsProvider::class,
            openapi: new Model\Operation(
                parameters: [
                    new Model\Parameter(
                        name: 'id[eq]',
                        in: 'query',
                        description: 'Filter by id using "equals" operator',
                        required: false,
                        schema: [
                            'type' => 'array',
                            'items' => ['type' => 'integer'],
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
                is_granted("' . ContactGroupPermissionEnum::CanRead->value . '") or
                is_granted("' . ContactGroupPermissionEnum::CanReadAndWrite->value . '")',
            securityMessage: 'You are not allowed to list contact groups',
        ),
    ],
)]
final class ContactGroupResource
{
    public function __construct(
        #[ApiProperty(identifier: true, writable: false)]
        public int $id,

        #[ApiProperty(
            description: 'The contact group name',
            openapiContext: ['example' => 'Supervisors']
        )]
        public string $name,
    ) {
    }
}
