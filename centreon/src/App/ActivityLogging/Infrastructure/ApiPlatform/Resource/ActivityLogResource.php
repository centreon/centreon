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

namespace App\ActivityLogging\Infrastructure\ApiPlatform\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model;
use App\ActivityLogging\Domain\Aggregate\ActionEnum;
use App\ActivityLogging\Domain\Aggregate\TargetTypeEnum;
use App\ActivityLogging\Domain\Security\ActivityLogPermissionEnum;
use App\ActivityLogging\Infrastructure\ApiPlatform\State\ListActivityLogsProvider;

#[GetCollection(
    shortName: 'ActivityLog',
    uriTemplate: '/administration/activity-logs',
    security: 'is_granted("' . ActivityLogPermissionEnum::CanRead->value . '")',
    securityMessage: 'You are not allowed to access the activity logs',
    paginationItemsPerPage: 30,
    // No total count: counting millions of logs on every page is too costly
    paginationPartial: true,
    provider: ListActivityLogsProvider::class,
    openapi: new Model\Operation(
        tags: ['Activity Log'],
        summary: 'List the configuration changes, most recent first by default',
        responses: [
            400 => new Model\Response('Invalid filter'),
            403 => new Model\Response('You are not allowed to access the activity logs'),
        ],
        parameters: [
            new Model\Parameter(
                name: 'object_name',
                in: 'query',
                description: 'Filter by target name (partial match)',
                required: false,
                schema: ['type' => 'string'],
            ),
            new Model\Parameter(
                name: 'actor_id[]',
                in: 'query',
                description: 'Filter by actor (contact) id',
                required: false,
                schema: ['type' => 'array', 'items' => ['type' => 'integer']],
            ),
            new Model\Parameter(
                name: 'target_type[]',
                in: 'query',
                description: 'Filter by target type',
                required: false,
                schema: ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ActivityLogResource::TARGET_TYPES]],
            ),
            new Model\Parameter(
                name: 'target_id',
                in: 'query',
                description: 'Filter by target id',
                required: false,
                schema: ['type' => 'integer'],
            ),
            new Model\Parameter(
                name: 'action[]',
                in: 'query',
                description: 'Filter by action',
                required: false,
                schema: ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ActivityLogResource::ACTIONS]],
            ),
            new Model\Parameter(
                name: 'date[from]',
                in: 'query',
                description: 'Only the changes performed at or after this ISO 8601 date',
                required: false,
                schema: ['type' => 'string', 'format' => 'date-time'],
            ),
            new Model\Parameter(
                name: 'date[to]',
                in: 'query',
                description: 'Only the changes performed at or before this ISO 8601 date',
                required: false,
                schema: ['type' => 'string', 'format' => 'date-time'],
            ),
            new Model\Parameter(
                name: 'sort[date]',
                in: 'query',
                description: 'Order of the changes by date (default "desc", most recent first)',
                required: false,
                schema: ['type' => 'string', 'enum' => ['asc', 'desc']],
            ),
        ],
    ),
)]
final class ActivityLogResource
{
    public const TARGET_TYPES = [
        TargetTypeEnum::Command->value,
        TargetTypeEnum::TimePeriod->value,
        TargetTypeEnum::Contact->value,
        TargetTypeEnum::ContactGroup->value,
        TargetTypeEnum::Host->value,
        TargetTypeEnum::HostGroup->value,
        TargetTypeEnum::Service->value,
        TargetTypeEnum::ServiceGroup->value,
        TargetTypeEnum::Trap->value,
        TargetTypeEnum::Escalation->value,
        TargetTypeEnum::HostDependency->value,
        TargetTypeEnum::HostGroupDependency->value,
        TargetTypeEnum::ServiceDependency->value,
        TargetTypeEnum::ServiceGroupDependency->value,
        TargetTypeEnum::Poller->value,
        TargetTypeEnum::Engine->value,
        TargetTypeEnum::Broker->value,
        TargetTypeEnum::Resource->value,
        TargetTypeEnum::MetaService->value,
        TargetTypeEnum::AccessGroup->value,
        TargetTypeEnum::MenuAccess->value,
        TargetTypeEnum::ResourceAccess->value,
        TargetTypeEnum::ActionAccess->value,
        TargetTypeEnum::Manufacturer->value,
        TargetTypeEnum::HostCategory->value,
        TargetTypeEnum::ServiceCategory->value,
        TargetTypeEnum::HostSeverity->value,
        TargetTypeEnum::ServiceSeverity->value,
    ];
    public const ACTIONS = [
        ActionEnum::Add->value,
        ActionEnum::Update->value,
        ActionEnum::Delete->value,
        ActionEnum::Enable->value,
        ActionEnum::Disable->value,
        ActionEnum::MassChange->value,
    ];

    public function __construct(
        #[ApiProperty(identifier: true, openapiContext: ['example' => 123])]
        public int $id,

        #[ApiProperty(description: 'When the change was performed')]
        public \DateTimeImmutable $date,

        #[ApiProperty(
            description: 'The kind of change',
            openapiContext: ['example' => 'Update', 'enum' => self::ACTIONS],
        )]
        public string $action,

        #[ApiProperty(description: 'The changed configuration object', genId: false)]
        public ActivityLogTargetOutput $target,

        #[ApiProperty(description: 'Who performed the change, null for a change made by the system', genId: false)]
        public ?ActivityLogActorOutput $actor,

        #[ApiProperty(description: 'Whether field changes were recorded for this log')]
        public bool $hasChanges,
    ) {
    }
}
