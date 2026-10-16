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

namespace Core\Broker\Infrastructure\Repository;

/**
 * Converts rows of the legacy cfg_centreonbroker_info table into broker inputs/outputs.
 *
 * The writers of that table do not agree on the grp_level/subgrp_id/parent_grp_id columns, so rows are
 * interpreted the way the broker configuration generation reads them:
 * - "name", "type" and "blockId" rows describe the input/output itself;
 * - rows with a fieldIndex are grouped fields ("<group>__<field>"), groups being ordered by first appearance;
 * - "category" and "event" rows are filter values, the empty "filters" parent rows are dropped;
 * - any other row is a simple parameter, the last row of a repeated key wins.
 *
 * Rows must be given in read order. Rows of other groups than input and output (legacy "logger") are dropped.
 *
 * @phpstan-import-type _StoredParameters from StoredBrokerInputOutput
 *
 * @phpstan-type _BrokerInfoRow array{
 *     config_group: string,
 *     config_group_id: int|string,
 *     config_key: string,
 *     config_value: string,
 *     subgrp_id?: int|string|null,
 *     fieldIndex?: int|string|null,
 *     ...
 * }
 */
final class BrokerInfoRowsConverter
{
    private const FILTER_KEYS = ['category', 'event'];
    private const FILTERS_PARENT_KEY = 'filters';

    /**
     * @param iterable<_BrokerInfoRow> $rows rows of one or several broker configurations sharing their ids
     *
     * @return list<StoredBrokerInputOutput>
     */
    public static function convert(iterable $rows): array
    {
        /** @var array<string, non-empty-list<_BrokerInfoRow>> $rowsByFlow */
        $rowsByFlow = [];
        foreach ($rows as $row) {
            if (! in_array($row['config_group'], ['input', 'output'], true)) {
                continue;
            }
            $rowsByFlow[$row['config_group'] . '_' . $row['config_group_id']][] = $row;
        }

        return array_values(array_map(self::convertFlow(...), $rowsByFlow));
    }

    /**
     * @param non-empty-list<_BrokerInfoRow> $rows
     */
    private static function convertFlow(array $rows): StoredBrokerInputOutput
    {
        $name = '';
        $type = null;
        $typeId = null;
        /** @var array<string, string> $parameters */
        $parameters = [];
        /** @var array<string, list<string>> $filters */
        $filters = [];
        /** @var array<string, array<int, array<string, string>>> $groups */
        $groups = [];

        foreach ($rows as $row) {
            $key = $row['config_key'];
            $value = $row['config_value'];
            $fieldIndex = $row['fieldIndex'] ?? null;

            if ($fieldIndex !== null && $fieldIndex !== '') {
                $keyParts = explode('__', $key, 3);
                $groups[$keyParts[0]][(int) $fieldIndex][$keyParts[1] ?? ''] = $value;

                continue;
            }

            if ($key === 'name') {
                $name = $value;
            } elseif ($key === 'type') {
                $type = $value;
            } elseif ($key === 'blockId') {
                $typeId = (int) preg_replace('/^[12]_/', '', $value);
            } elseif (in_array($key, self::FILTER_KEYS, true)) {
                $filters[self::FILTERS_PARENT_KEY . '_' . $key][] = $value;
            } elseif (! self::isFiltersParentRow($row)) {
                $parameters[$key] = $value;
            }
        }

        $parameters = [...$parameters, ...$filters, ...array_map(array_values(...), $groups)];

        $tag = $rows[0]['config_group'] === 'input' ? 'input' : 'output';

        return new StoredBrokerInputOutput($tag, (int) $rows[0]['config_group_id'], $name, $type, $typeId, $parameters);
    }

    /**
     * @param _BrokerInfoRow $row
     */
    private static function isFiltersParentRow(array $row): bool
    {
        return ($row['subgrp_id'] ?? null) !== null
            || ($row['config_key'] === self::FILTERS_PARENT_KEY && $row['config_value'] === '');
    }
}
