<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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
use App\ActivityLogging\Domain\Security\ActivityLogPermissionEnum;
use App\ActivityLogging\Infrastructure\ApiPlatform\State\ListActivityLogTokensProvider;

#[GetCollection(
    shortName: 'ActivityLogToken',
    uriTemplate: '/administration/activity-logs/tokens',
    security: 'is_granted("' . ActivityLogPermissionEnum::CanRead->value . '")',
    securityMessage: 'You are not allowed to access the activity logs',
    paginationEnabled: false,
    provider: ListActivityLogTokensProvider::class,
    openapi: new Model\Operation(
        tags: ['Activity Log'],
        summary: 'List the API tokens at least one logged change was made with, sorted by name',
        responses: [
            403 => new Model\Response('You are not allowed to access the activity logs'),
        ],
    ),
)]
final class ActivityLogTokenResource
{
    public function __construct(
        #[ApiProperty(identifier: true, openapiContext: ['example' => 'ci-deploy'])]
        public string $name,
    ) {
    }
}
