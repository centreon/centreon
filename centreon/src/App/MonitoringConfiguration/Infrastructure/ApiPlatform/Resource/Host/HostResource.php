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
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\OpenApi\Model;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SnmpVersionEnum;
use App\MonitoringConfiguration\Domain\Security\HostPermissionEnum;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\CreateHostInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\PatchHostInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\UpdateHostInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\CreateHostProcessor;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\DeleteHostProcessor;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\ListHostsProvider;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\PatchHostProcessor;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\PutHostProcessor;

#[ApiResource(
    shortName: 'Host',
    operations: [
        new Post(
            uriTemplate: '/configuration/hosts',
            openapi: new Model\Operation(
                responses: [
                    404 => new Model\Response('Poller or host group not found'),
                    409 => new Model\Response('Host resource already exists'),
                    422 => new Model\Response('Invalid input'),
                ],
            ),
            security: "is_granted('" . HostPermissionEnum::CanReadAndWrite->value . "')",
            securityMessage: 'You are not allowed to create hosts',
            input: CreateHostInput::class,
            processor: CreateHostProcessor::class,
        ),
        new Patch(
            uriTemplate: '/configuration/hosts/{id}',
            status: 204,
            // Write-only action: no item provider, the handler loads the host itself and answers 404
            // when it is missing or outside the viewer's scope.
            read: false,
            processor: PatchHostProcessor::class,
            input: PatchHostInput::class,
            output: false,
            openapi: new Model\Operation(
                description: 'Partially updates a host: a key left out is left untouched, a key sent as null clears the value when it can be cleared. The host groups, categories, templates, parents, children, contacts and macros are not handled yet.',
                responses: [
                    204 => new Model\Response('Host updated (or left as it was when nothing changed)'),
                    404 => new Model\Response('Host not found'),
                    409 => new Model\Response('A host or host template already uses this name'),
                    422 => new Model\Response('Invalid input'),
                    502 => new Model\Response('The vault could not be reached'),
                ],
            ),
            security: "is_granted('" . HostPermissionEnum::CanReadAndWrite->value . "')",
            securityMessage: 'You are not allowed to update hosts',
        ),
        new Put(
            uriTemplate: '/configuration/hosts/{id}',
            // Write-only replace: the processor loads the host itself (404 via the handler when it is
            // missing or out of the viewer's ACL scope), so no item provider is read; it returns the
            // full updated resource.
            read: false,
            processor: PutHostProcessor::class,
            input: UpdateHostInput::class,
            openapi: new Model\Operation(
                description: 'Fully update a single host (replace semantics).',
                responses: [
                    404 => new Model\Response('Host not found'),
                    409 => new Model\Response('Host name already used by another host or template'),
                    422 => new Model\Response('Invalid input, or a referenced resource that does not exist or is not accessible'),
                ],
            ),
            security: "is_granted('" . HostPermissionEnum::CanReadAndWrite->value . "')",
            securityMessage: 'You are not allowed to update hosts',
        ),
        new GetCollection(
            uriTemplate: '/configuration/hosts',
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
            output: HostCollectionOutput::class,
            provider: ListHostsProvider::class,
        ),
        new Delete(
            uriTemplate: '/configuration/hosts/{id}',
            processor: DeleteHostProcessor::class,
            read: false,
            security: "is_granted('" . HostPermissionEnum::CanReadAndWrite->value . "')",
            securityMessage: 'You are not allowed to delete hosts',
        ),
    ],
)]
final class HostResource
{
    public HostPollerOutput $poller;

    /** @var list<HostTemplateOutput> */
    public array $templates;

    /** @var list<HostGroupOutput> */
    public array $groups;

    public DataProcessingOutput $dataProcessing;

    /** @var list<HostCategoryOutput> */
    public array $categories = [];

    /** @var list<RelatedHostOutput> */
    public array $parentHosts = [];

    /** @var list<RelatedHostOutput> */
    public array $childHosts = [];

    public ?HostTimezoneOutput $timezone = null;

    public ?HostSeverityOutput $severity = null;

    public ?HostExtendedInformationsOutput $extendedInformations = null;

    public HostSchedulingOptionsOutput $schedulingOptions;

    public HostCheckOptionsOutput $checkOptions;

    public ?HostNotificationsOutput $notifications = null;

    public function __construct(
        #[ApiProperty(identifier: true, writable: false)]
        public int $id,

        public string $name,

        public ?string $alias,

        public string $address,

        public bool $activated,

        public ?SnmpVersionEnum $snmpVersion = null,
    ) {
    }
}
