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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use App\MonitoringConfiguration\Domain\Security\HostPermissionEnum;
use App\MonitoringConfiguration\Domain\Security\ServicePermissionEnum;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service\CreateServiceProcessor;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service\DeleteServiceProcessor;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service\DeployHostServicesProcessor;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service\GetServiceProvider;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service\ListServicesProvider;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service\PatchServiceProcessor;

#[ApiResource(
    shortName: 'Service',
    operations: [
        new GetCollection(
            uriTemplate: '/configuration/services',
            itemUriTemplate: '/configuration/services/{id}',
            openapi: new Model\Operation(
                summary: 'List the services',
                description: 'Services linked to host groups are not listed. The check intervals and the icon are the '
                    . 'effective ones, inherited from the service templates when the service has none of its own; '
                    . 'intervals are in engine units.',
                parameters: [
                    new Model\Parameter(
                        name: 'host_name[lk]',
                        in: 'query',
                        description: 'Filter by host name using "like" operator',
                        required: false,
                        schema: ['type' => 'string'],
                    ),
                    new Model\Parameter(
                        name: 'name[lk]',
                        in: 'query',
                        description: 'Filter by service name using "like" operator',
                        required: false,
                        schema: ['type' => 'string'],
                    ),
                    new Model\Parameter(
                        name: 'template_id',
                        in: 'query',
                        description: 'Filter by direct service template id',
                        required: false,
                        schema: ['type' => 'integer'],
                    ),
                    new Model\Parameter(
                        name: 'activated',
                        in: 'query',
                        description: 'Filter by service activation status',
                        required: false,
                        schema: ['type' => 'boolean'],
                    ),
                    new Model\Parameter(
                        name: 'host_activated',
                        in: 'query',
                        description: 'Filter by host activation status; all hosts when absent',
                        required: false,
                        schema: ['type' => 'boolean'],
                    ),
                    new Model\Parameter(
                        name: 'sort',
                        in: 'query',
                        description: 'Sort by one or several fields, in order of precedence (e.g. `sort[host_name]=asc`). '
                            . 'Default: host name, then service name.',
                        required: false,
                        style: 'deepObject',
                        explode: true,
                        schema: [
                            'type' => 'object',
                            'properties' => [
                                'host_name' => ['type' => 'string', 'enum' => ['asc', 'desc']],
                                'name' => ['type' => 'string', 'enum' => ['asc', 'desc']],
                            ],
                        ],
                    ),
                ],
            ),
            security: '
                is_granted("' . ServicePermissionEnum::CanRead->value . '") or
                is_granted("' . ServicePermissionEnum::CanReadAndWrite->value . '")',
            securityMessage: 'You are not allowed to list services',
            output: ServiceCollectionOutput::class,
            provider: ListServicesProvider::class,
        ),
        new Post(
            uriTemplate: '/configuration/services',
            openapi: new Model\Operation(
                summary: 'Create a service',
                description: 'Creates a service linked directly to one host, enabled, and answers its form as a Get '
                    . 'does. Its own macros are not taken yet.',
                responses: [
                    403 => new Model\Response('You are not allowed to create services'),
                    404 => new Model\Response('A nested reference (command, time period, icon, contact, contact group) '
                        . 'no longer exists'),
                    409 => new Model\Response('The name was taken on the host in the meantime'),
                    422 => new Model\Response('Invalid input'),
                ],
            ),
            security: "is_granted('" . ServicePermissionEnum::CanReadAndWrite->value . "')",
            securityMessage: 'You are not allowed to create services',
            input: CreateServiceInput::class,
            processor: CreateServiceProcessor::class,
        ),
        new Post(
            uriTemplate: '/configuration/hosts/{hostId}/services/deploy',
            requirements: ['hostId' => '[1-9]\d*'],
            uriVariables: ['hostId'],
            normalizationContext: ['api_sub_level' => true],
            read: false,
            deserialize: false,
            validate: false,
            input: false,
            processor: DeployHostServicesProcessor::class,
            openapi: new Model\Operation(
                summary: 'Deploy the services of a host from its templates',
                parameters: [
                    new Model\Parameter(name: 'hostId', in: 'path', description: 'Host identifier', required: true, schema: ['type' => 'string']),
                ],
                description: 'Creates on the host one enabled service per service template of its host templates and '
                    . 'their ancestors, named after the template alias, with the service groups of the template, '
                    . 'unless a service of the host already has that name (case ignored). All or '
                    . 'nothing: an invalid alias fails the whole deployment. Answers the created services, each as '
                    . 'a Get does.',
                responses: [
                    201 => new Model\Response(
                        'The created services, each as a Get answers it',
                        new \ArrayObject([
                            'application/ld+json' => ['schema' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Service.jsonld']]],
                            'application/json' => ['schema' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Service']]],
                        ]),
                    ),
                    204 => new Model\Response('Nothing to deploy: no service template, or every name already on the host'),
                    403 => new Model\Response('You are not allowed to deploy services'),
                    404 => new Model\Response('Host not found'),
                    422 => new Model\Response(
                        'A service template alias cannot name a service; nothing was deployed',
                        new \ArrayObject([
                            'application/json' => ['schema' => [
                                'type' => 'object',
                                'properties' => ['code' => ['type' => 'integer'], 'message' => ['type' => 'string']],
                            ]],
                        ]),
                    ),
                ],
            ),
            security: '
                is_granted("' . HostPermissionEnum::CanRead->value . '") or
                is_granted("' . HostPermissionEnum::CanReadAndWrite->value . '")',
            securityMessage: 'You are not allowed to deploy services',
        ),
        new Get(
            uriTemplate: '/configuration/services/{id}',
            // Positive integers only, so the selector routes under /configuration/services (hosts, commands, ...)
            // keep matching their own operations, and `/0` is a 404 too.
            requirements: ['id' => '[1-9]\d*'],
            provider: GetServiceProvider::class,
            openapi: new Model\Operation(
                summary: 'Get a service',
                description: 'Every field of the service form, as stored on the service itself: nothing inherited '
                    . 'from its templates is resolved, except the macros, which are the effective ones (its own, '
                    . 'then those it inherits, each with the id and parent to send back to override it). A password '
                    . "macro's value is never returned. Services linked to host groups are not found.",
                responses: [
                    403 => new Model\Response('You are not allowed to view services'),
                    404 => new Model\Response('Service not found'),
                ],
            ),
            security: '
                is_granted("' . ServicePermissionEnum::CanRead->value . '") or
                is_granted("' . ServicePermissionEnum::CanReadAndWrite->value . '")',
            securityMessage: 'You are not allowed to view services',
        ),
        new Delete(
            uriTemplate: '/configuration/services/{id}',
            // Positive integers only, as on the item IRI: a non-numeric or zero id is a 404, not a 500.
            requirements: ['id' => '[1-9]\d*'],
            processor: DeleteServiceProcessor::class,
            read: false,
            security: "is_granted('" . ServicePermissionEnum::CanReadAndWrite->value . "')",
            securityMessage: 'You are not allowed to delete services',
        ),
        new Patch(
            uriTemplate: '/configuration/services/{id}',
            // Positive integers only, as on the item IRI: a non-numeric or zero id is a 404, not a 500.
            requirements: ['id' => '[1-9]\d*'],
            status: 204,
            // Write-only action: no item provider, the handler loads the service itself and answers 404
            // when it is missing or outside the actor's scope.
            read: false,
            processor: PatchServiceProcessor::class,
            input: PatchServiceInput::class,
            output: false,
            openapi: new Model\Operation(
                description: 'Enables or disables a service.',
                responses: [
                    204 => new Model\Response('Service updated (or left as it was when nothing changed)'),
                    403 => new Model\Response('You are not allowed to update services'),
                    404 => new Model\Response('Service not found'),
                    422 => new Model\Response('Invalid input'),
                ],
            ),
            security: "is_granted('" . ServicePermissionEnum::CanReadAndWrite->value . "')",
            securityMessage: 'You are not allowed to update services',
        ),
    ],
)]
final class ServiceResource
{
    public ServiceHostOutput $host;

    public ?ServiceTemplateOutput $template = null;

    public ServiceCheckOptionsOutput $checkOptions;

    public ServiceMonitoringEngineOutput $monitoringEngine;

    public ?ServiceNotificationsOutput $notifications = null;

    public ServiceAdvancedOutput $advanced;

    /** @var list<ServiceGroupOutput> */
    public array $serviceGroups = [];

    /** @var list<ServiceCategoryOutput> */
    public array $categories = [];

    public ?ServiceSeverityOutput $severity = null;

    public ?ServiceGraphTemplateOutput $graphTemplate = null;

    /** @var ?list<ServiceTrapOutput> null, and so left out, on a Cloud platform */
    public ?array $traps = [];

    public function __construct(
        #[ApiProperty(identifier: true, writable: false)]
        public int $id,
        public string $name,
        public bool $activated,
    ) {
    }
}
