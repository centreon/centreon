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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model;
use App\MonitoringConfiguration\Domain\Security\HostPermissionEnum;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\ListHostsProvider;

#[ApiResource(
    shortName: 'Host',
    operations: [
        new GetCollection(
            uriTemplate: '/configuration/hosts',
            provider: ListHostsProvider::class,
            output: HostCollectionOutput::class,
            openapi: new Model\Operation(
                parameters: [
                    new Model\Parameter(
                        name: 'name[lk]',
                        in: 'query',
                        description: 'Filter by host name using "like" operator',
                        required: false,
                        schema: ['type' => 'string'],
                    ),
                    new Model\Parameter(
                        name: 'template_id',
                        in: 'query',
                        description: 'Filter by host template id',
                        required: false,
                        schema: ['type' => 'integer'],
                    ),
                    new Model\Parameter(
                        name: 'group_id',
                        in: 'query',
                        description: 'Filter by host group id',
                        required: false,
                        schema: ['type' => 'integer'],
                    ),
                    new Model\Parameter(
                        name: 'poller_id',
                        in: 'query',
                        description: 'Filter by poller id',
                        required: false,
                        schema: ['type' => 'integer'],
                    ),
                    new Model\Parameter(
                        name: 'activated',
                        in: 'query',
                        description: 'Filter by activation status',
                        required: false,
                        schema: ['type' => 'boolean'],
                    ),
                ],
            ),
            security: '
                is_granted("' . HostPermissionEnum::CanRead->value . '") or
                is_granted("' . HostPermissionEnum::CanReadAndWrite->value . '")',
            securityMessage: 'You are not allowed to list hosts',
        ),
    ],
)]
final class HostResource
{
    /**
     * @param list<HostTemplateOutput> $templates
     */
    public function __construct(
        #[ApiProperty(identifier: true, writable: false)]
        public int $id,

        public string $name,

        public ?string $alias,

        public string $address,

        public HostPollerOutput $poller,

        public array $templates,

        public bool $activated,
    ) {
    }
}
