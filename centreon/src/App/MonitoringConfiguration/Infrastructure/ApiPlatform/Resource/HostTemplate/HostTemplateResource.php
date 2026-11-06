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
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model;
use App\MonitoringConfiguration\Domain\Aggregate\SnmpVersionEnum;
use App\MonitoringConfiguration\Domain\Security\HostPermissionEnum;
use App\MonitoringConfiguration\Domain\Security\HostTemplatePermissionEnum;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\DataProcessingOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostCategoryOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostCheckOptionsOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostNotificationsOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostSchedulingOptionsOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostSeverityOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostTemplateOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostTimezoneOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\HostTemplate\CreateHostTemplateProcessor;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\HostTemplate\DeleteHostTemplateProcessor;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\HostTemplate\DuplicateHostTemplateProcessor;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\HostTemplate\GetHostTemplateMacrosProvider;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\HostTemplate\GetHostTemplateProvider;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\HostTemplate\ListHostTemplatesChoicesProvider;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\HostTemplate\ListHostTemplatesProvider;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\HostTemplate\PatchHostTemplateProcessor;

#[ApiResource(
    shortName: 'HostTemplate',
    operations: [
        new GetCollection(
            uriTemplate: '/configuration/hosts/host_templates',
            openapi: false,
            security: 'is_granted("' . HostPermissionEnum::CanReadAndWrite->value . '")',
            securityMessage: 'You are not allowed to access host templates',
            itemUriTemplate: '/configuration/host_templates/{id}',
            output: HostTemplateChoicesOutput::class,
            provider: ListHostTemplatesChoicesProvider::class,
        ),
        // A host template's own and inherited macros, read to prefill a host before it is created.
        // Same family as the host template choices list above, so it takes the same right, not a
        // host template one.
        new Get(
            uriTemplate: '/configuration/hosts/host_templates/{id}/macros',
            requirements: ['id' => '[1-9]\d*'],
            output: HostTemplateMacrosOutput::class,
            provider: GetHostTemplateMacrosProvider::class,
            openapi: false,
            security: 'is_granted("' . HostPermissionEnum::CanReadAndWrite->value . '")',
            securityMessage: 'You are not allowed to access host templates',
        ),
        new GetCollection(
            uriTemplate: '/configuration/host_templates/host_templates',
            openapi: false,
            security: 'is_granted("' . HostTemplatePermissionEnum::CanReadAndWrite->value . '")',
            securityMessage: 'You are not allowed to access host templates',
            itemUriTemplate: '/configuration/host_templates/{id}',
            output: HostTemplateChoicesOutput::class,
            provider: ListHostTemplatesChoicesProvider::class,
            parameters: ['exclude_template_id' => new QueryParameter(schema: ['type' => 'integer', 'minimum' => 1], castToNativeType: true)],
        ),
        new Get(
            uriTemplate: '/configuration/host_templates/host_templates/{id}/macros',
            requirements: ['id' => '[1-9]\d*'],
            output: HostTemplateMacrosOutput::class,
            provider: GetHostTemplateMacrosProvider::class,
            openapi: false,
            security: 'is_granted("' . HostTemplatePermissionEnum::CanReadAndWrite->value . '")',
            securityMessage: 'You are not allowed to access host templates',
        ),
        new GetCollection(
            uriTemplate: '/configuration/host_templates',
            provider: ListHostTemplatesProvider::class,
            itemUriTemplate: '/configuration/host_templates/{id}',
            output: HostTemplateCollectionOutput::class,
            paginationMaximumItemsPerPage: 100,
            openapi: new Model\Operation(
                parameters: [
                    new Model\Parameter(
                        name: 'name[lk]',
                        in: 'query',
                        description: 'Filter by host template name or alias using "like" operator',
                        required: false,
                        schema: ['type' => 'string'],
                    ),
                    new Model\Parameter(
                        name: 'include_locked',
                        in: 'query',
                        description: 'Include the locked host templates as well (unlocked ones only when absent or false)',
                        required: false,
                        schema: ['type' => 'boolean'],
                    ),
                    new Model\Parameter(
                        name: 'sort[name]',
                        in: 'query',
                        description: 'Sort by name (default: name ascending)',
                        required: false,
                        schema: ['type' => 'string', 'enum' => ['asc', 'desc']],
                    ),
                    new Model\Parameter(
                        name: 'sort[alias]',
                        in: 'query',
                        description: 'Sort by alias',
                        required: false,
                        schema: ['type' => 'string', 'enum' => ['asc', 'desc']],
                    ),
                ],
            ),
            security: '
                is_granted("' . HostTemplatePermissionEnum::CanRead->value . '") or
                is_granted("' . HostTemplatePermissionEnum::CanReadAndWrite->value . '")',
            securityMessage: 'You are not allowed to list host templates',
        ),
        new Post(
            uriTemplate: '/configuration/host_templates',
            itemUriTemplate: '/configuration/host_templates/{id}',
            input: CreateHostTemplateInput::class,
            processor: CreateHostTemplateProcessor::class,
            denormalizationContext: ['allow_extra_attributes' => false],
            openapi: new Model\Operation(
                summary: 'Create a host template',
                responses: [
                    400 => new Model\Response('A field the host template does not have'),
                    401 => new Model\Response('Authentication required'),
                    403 => new Model\Response('You are not allowed to create host templates'),
                    409 => new Model\Response('A host or host template already uses this name'),
                    422 => new Model\Response('Invalid value, or a reference that does not exist or cannot be used'),
                    502 => new Model\Response('The vault could not be reached'),
                ],
            ),
            security: 'is_granted("' . HostTemplatePermissionEnum::CanReadAndWrite->value . '")',
            securityMessage: 'You are not allowed to create host templates',
        ),
        new Post(
            uriTemplate: '/configuration/host_templates/{id}/_duplicate',
            requirements: ['id' => '[1-9]\d*'],
            // The handler loads the source (404).
            read: false,
            // Optional body: read by the processor, see DuplicateHostTemplateProcessor.
            deserialize: false,
            validate: false,
            input: DuplicateHostTemplateInput::class,
            output: false,
            status: 204,
            processor: DuplicateHostTemplateProcessor::class,
            openapi: new Model\Operation(
                summary: 'Duplicate a host template',
                description: 'Duplicates a single host template. The copy takes the given name, or, when none is '
                    . 'given, the source name with the first free "_<n>" suffix.',
                responses: [
                    204 => new Model\Response('Host template duplicated'),
                    400 => new Model\Response('The host template is locked, or the body is not a JSON object'),
                    403 => new Model\Response('You are not allowed to duplicate host templates'),
                    404 => new Model\Response('Host template not found'),
                    409 => new Model\Response('A host or host template already uses this name'),
                    422 => new Model\Response('Invalid name, or the generated name would exceed the maximum length'),
                    502 => new Model\Response('The vault could not be reached'),
                ],
                requestBody: new Model\RequestBody(
                    description: 'Optional: without a body, or without a name, the copy is named "<source name>_<n>".',
                    required: false,
                ),
            ),
            security: 'is_granted("' . HostTemplatePermissionEnum::CanReadAndWrite->value . '")',
            securityMessage: 'You are not allowed to duplicate host templates',
        ),
        new Patch(
            uriTemplate: '/configuration/host_templates/{id}',
            requirements: ['id' => '[1-9]\d*'],
            status: 204,
            read: false,
            input: PatchHostTemplateInput::class,
            output: false,
            processor: PatchHostTemplateProcessor::class,
            denormalizationContext: ['allow_extra_attributes' => false],
            openapi: new Model\Operation(
                summary: 'Update a host template',
                description: 'A key left out leaves its value untouched; a key sent as null clears its value where it can be cleared.',
                responses: [
                    204 => new Model\Response('Host template updated'),
                    400 => new Model\Response('The host template is locked, or a field the host template does not have'),
                    401 => new Model\Response('Authentication required'),
                    403 => new Model\Response('You are not allowed to update host templates'),
                    404 => new Model\Response('Host template not found'),
                    409 => new Model\Response('A host or host template already uses this name'),
                    415 => new Model\Response('The body is not sent as application/merge-patch+json'),
                    422 => new Model\Response('Invalid value, or a reference that does not exist or cannot be used'),
                    502 => new Model\Response('The vault could not be reached'),
                ],
            ),
            security: 'is_granted("' . HostTemplatePermissionEnum::CanReadAndWrite->value . '")',
            securityMessage: 'You are not allowed to update host templates',
        ),
        new Delete(
            uriTemplate: '/configuration/host_templates/{id}',
            requirements: ['id' => '[1-9]\d*'],
            read: false,
            output: false,
            processor: DeleteHostTemplateProcessor::class,
            openapi: new Model\Operation(
                summary: 'Delete a host template',
                responses: [
                    400 => new Model\Response('The host template is locked'),
                    403 => new Model\Response('You are not allowed to delete host templates'),
                    404 => new Model\Response('Host template not found'),
                    502 => new Model\Response('The host template was deleted but its vault entry could not be purged'),
                ],
            ),
            security: 'is_granted("' . HostTemplatePermissionEnum::CanReadAndWrite->value . '")',
            securityMessage: 'You are not allowed to delete host templates',
        ),
        new Get(
            uriTemplate: '/configuration/host_templates/{id}',
            requirements: ['id' => '[1-9]\d*'],
            provider: GetHostTemplateProvider::class,
            openapi: new Model\Operation(
                summary: 'Get a host template',
                responses: [
                    401 => new Model\Response('Authentication required'),
                    403 => new Model\Response('You are not allowed to view host templates'),
                    404 => new Model\Response('Host template not found'),
                ],
            ),
            security: '
                is_granted("' . HostTemplatePermissionEnum::CanRead->value . '") or
                is_granted("' . HostTemplatePermissionEnum::CanReadAndWrite->value . '")',
            securityMessage: 'You are not allowed to view host templates',
        ),
    ],
)]
final class HostTemplateResource
{
    public ?HostTimezoneOutput $timezone = null;

    public ?HostSeverityOutput $severity = null;

    /** @var list<HostCategoryOutput> */
    public array $categories = [];

    /** @var list<HostTemplateOutput> in relation order */
    public array $templates = [];

    /** @var list<HostTemplateLinkedServiceTemplateOutput> */
    public array $linkedServiceTemplates = [];

    public HostCheckOptionsOutput $checkOptions;

    public ?HostTemplateExtendedInformationsOutput $extendedInformations = null;

    public HostSchedulingOptionsOutput $schedulingOptions;

    public DataProcessingOutput $dataProcessing;

    public ?HostNotificationsOutput $notifications = null;

    public function __construct(
        #[ApiProperty(identifier: true, writable: false)]
        public int $id,

        public string $name,

        public ?string $alias = null,

        #[ApiProperty(writable: false)]
        public bool $isLocked = false,

        public ?SnmpVersionEnum $snmpVersion = null,

        #[ApiProperty(description: 'Whether an SNMP community is set. The community itself is never returned.', writable: false)]
        public bool $isSnmpCommunityDefined = false,
    ) {
    }
}
