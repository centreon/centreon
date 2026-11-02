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
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model;
use App\ActivityLogging\Domain\Security\ActivityLogPermissionEnum;
use App\ActivityLogging\Infrastructure\ApiPlatform\State\ListActivityLogChangesProvider;

#[GetCollection(
    shortName: 'ActivityLogChange',
    uriTemplate: '/administration/activity-logs/{id}/changes',
    uriVariables: [
        'id' => new Link(fromClass: ActivityLogResource::class, identifiers: ['id']),
    ],
    requirements: ['id' => '\d+'],
    security: 'is_granted("' . ActivityLogPermissionEnum::CanRead->value . '")',
    securityMessage: 'You are not allowed to access the activity logs',
    paginationEnabled: false,
    provider: ListActivityLogChangesProvider::class,
    openapi: new Model\Operation(
        tags: ['Activity Log'],
        summary: 'List the field changes recorded by an activity log',
        responses: [
            403 => new Model\Response('You are not allowed to access the activity logs'),
            404 => new Model\Response('Activity log not found'),
        ],
    ),
)]
final class ActivityLogChangeResource
{
    public function __construct(
        #[ApiProperty(openapiContext: ['example' => 'host_name'])]
        public string $field,

        #[ApiProperty(description: 'The value before the change, null when there was none', openapiContext: ['example' => 'old'])]
        public ?string $before,

        #[ApiProperty(description: 'The value after the change, null when emptied', openapiContext: ['example' => 'new'])]
        public ?string $after,
    ) {
    }
}
