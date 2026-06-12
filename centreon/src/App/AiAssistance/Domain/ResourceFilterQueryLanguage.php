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

namespace App\AiAssistance\Domain;

/**
 * Server-side knowledge of the Resources Status search query language
 * (www/front_src/src/Resources/Filter/Criterias/searchQueryLanguage),
 * used to validate generated search expressions.
 */
final class ResourceFilterQueryLanguage
{
    private const CRITERIA_KEYS = [
        'type',
        'status',
        'state',
        'status_type',
        'host_group',
        'service_group',
        'host_category',
        'service_category',
        'monitoring_server',
        'host_severity',
        'service_severity',
        'host_severity_level',
        'service_severity_level',
        'name',
        'parent_name',
    ];
    private const RAW_SEARCH_FIELDS = [
        'h.name',
        'h.alias',
        'h.address',
        's.description',
        'alias',
        'parent_alias',
        'fqdn',
        'information',
    ];
    private const STATIC_CRITERIA_VALUES = [
        'type' => ['host', 'service', 'metaservice'],
        'status' => ['ok', 'up', 'warning', 'down', 'critical', 'unreachable', 'unknown', 'pending'],
        'state' => ['unhandled', 'acknowledged', 'in_downtime', 'in_flapping'],
        'status_type' => ['hard', 'soft'],
    ];

    /**
     * @return string|null an error description, or null when the expression is valid
     */
    public static function validate(string $search): ?string
    {
        $search = trim($search);
        if ($search === '') {
            return null;
        }

        foreach (explode(' ', $search) as $token) {
            if ($token === '' || ! str_contains($token, ':')) {
                continue; // free-text token
            }

            [$key, $values] = explode(':', $token, 2);

            if (in_array($key, self::RAW_SEARCH_FIELDS, true)) {
                continue;
            }

            if (! in_array($key, self::CRITERIA_KEYS, true)) {
                return sprintf(
                    "unknown key '%s': allowed keys are %s and raw search fields %s",
                    $key,
                    implode(', ', self::CRITERIA_KEYS),
                    implode(', ', self::RAW_SEARCH_FIELDS)
                );
            }

            if ($values === '') {
                return sprintf("key '%s' has no value", $key);
            }

            $allowedValues = self::STATIC_CRITERIA_VALUES[$key] ?? null;
            if ($allowedValues === null) {
                continue;
            }

            foreach (explode(',', $values) as $value) {
                if (! in_array($value, $allowedValues, true)) {
                    return sprintf(
                        "invalid value '%s' for key '%s': allowed values are %s",
                        $value,
                        $key,
                        implode(', ', $allowedValues)
                    );
                }
            }
        }

        return null;
    }
}
