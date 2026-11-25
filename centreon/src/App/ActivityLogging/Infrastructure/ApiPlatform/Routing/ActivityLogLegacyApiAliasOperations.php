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

namespace App\ActivityLogging\Infrastructure\ApiPlatform\Routing;

use App\Shared\Infrastructure\ApiPlatform\Routing\LegacyApiAliasOperationProviderInterface;

/**
 * Activity log operations reachable under /api/latest, where the legacy frontend calls them.
 */
final class ActivityLogLegacyApiAliasOperations implements LegacyApiAliasOperationProviderInterface
{
    public function getOperationNames(): array
    {
        return [
            '_api_/administration/activity-logs_get_collection',
            '_api_/administration/activity-logs/{id}/changes_get_collection',
            '_api_/administration/activity-logs/actors_get_collection',
            '_api_/administration/activity-logs/tokens_get_collection',
        ];
    }
}
