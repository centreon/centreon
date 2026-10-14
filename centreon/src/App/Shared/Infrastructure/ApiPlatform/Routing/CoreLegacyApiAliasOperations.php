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

namespace App\Shared\Infrastructure\ApiPlatform\Routing;

/**
 * Core monorepo operations that keep their backward-compatible /api/latest alias.
 */
final class CoreLegacyApiAliasOperations implements LegacyApiAliasOperationProviderInterface
{
    public function getOperationNames(): array
    {
        return [
            '_api_/configuration/agent-configurations/installation-command/{pollerId}_get',
            '_api_/configuration/commands_get_collection',
            '_api_/configuration/commands_post',
            '_api_/configuration/commands/_duplicate_post',
            '_api_/configuration/commands/{id}_get',
            '_api_/configuration/commands/{id}_patch',
            '_api_/configuration/commands/{id}_delete',
            '_api_/configuration/connectors_get_collection',
            '_api_/configuration/connectors/{id}_get',
            '_api_/configuration/global-macros_get_collection',
            '_api_/configuration/plugins_get_collection',
            '_api_/configuration/plugins/{plugin_name}_get',
            '_api_/configuration/pollers_post',
            '_api_/configuration/pollers/installation-command/{id}_get',
            '_api_/configuration/services/categories_post',
            '_api_/configuration/standard-macros_get_collection',
            '_api_/platform/updates_post',
        ];
    }
}
