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
 * Contributes API Platform operation names that must keep a backward-compatible /api/latest alias.
 *
 * The core provides its own implementation; any module may ship one too. Every service implementing
 * this interface is collected by {@see LegacyApiPrefixAliasLoader} through the {@see self::TAG} tag
 * (applied automatically by the new kernel's autoconfiguration), so a module extends the allowlist
 * without touching core code.
 */
interface LegacyApiAliasOperationProviderInterface
{
    /**
     * Autoconfiguration tag collecting every provider for the legacy alias loader.
     */
    public const TAG = 'app.legacy_api_alias_operation_provider';

    /**
     * Allowlist operation names (the route default `_api_operation_name`) rather than resources: a
     * resource also carries the item operations API Platform generates on its own (/pollers/{id},
     * /global_macros/{id}, ...), which never existed under /api/latest and must not be aliased there.
     *
     * @return list<string>
     */
    public function getOperationNames(): array;
}
