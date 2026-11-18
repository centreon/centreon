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
 * A broker input/output as stored in cfg_centreonbroker_input_output.
 *
 * Parameters use the shape of the broker input/output API:
 * - simple parameters: "key" => "value"
 * - multiselect filters: "filters_<key>" => list of values
 * - grouped fields: "<group>" => list of ["<field>" => "value"]
 *
 * @phpstan-type _StoredParameters array<string, string|list<string>|list<array<string, string>>>
 * @phpstan-type _Record array{
 *     config_id?: int|string,
 *     tag: string,
 *     input_output_id: int|string,
 *     name: string,
 *     type_id: int|string|null,
 *     type: string|null,
 *     parameters: string,
 *     ...
 * }
 * @phpstan-type _LegacyRow array{
 *     config_group: 'input'|'output',
 *     config_group_id: int,
 *     config_key: string,
 *     config_value: string,
 *     grp_level: int,
 *     subgrp_id: int|null,
 *     parent_grp_id: int|null,
 *     fieldIndex: int|null,
 * }
 */
final readonly class StoredBrokerInputOutput
{
    /**
     * @param 'input'|'output' $tag
     * @param string|null $type the type short name, null when unknown
     * @param int|null $typeId the cb_type id, null when unknown
     * @param _StoredParameters $parameters
     */
    public function __construct(
        public string $tag,
        public int $id,
        public string $name,
        public ?string $type,
        public ?int $typeId,
        public array $parameters,
    ) {
    }

    /**
     * @param _Record $record a row of cfg_centreonbroker_input_output
     *
     * @throws \JsonException
     */
    public static function fromRecord(array $record): self
    {
        $parameters = json_decode($record['parameters'], true, flags: JSON_THROW_ON_ERROR);

        /** @var _StoredParameters $parameters */
        return new self(
            $record['tag'] === 'input' ? 'input' : 'output',
            (int) $record['input_output_id'],
            $record['name'],
            $record['type'],
            $record['type_id'] === null ? null : (int) $record['type_id'],
            is_array($parameters) ? $parameters : [],
        );
    }

    /**
     * @throws \JsonException
     */
    public function encodeParameters(): string
    {
        return json_encode(
            $this->parameters === [] ? new \stdClass() : $this->parameters,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * Expands the input/output into rows of the former cfg_centreonbroker_info table, for the legacy code
     * built on that format. Rows come in the order written by the broker input/output API: "type", "name"
     * and "blockId" first, then simple parameters, filters and grouped fields.
     *
     * @return list<_LegacyRow>
     */
    public function toLegacyRows(): array
    {
        $rows = [];
        if ($this->type !== null) {
            $rows[] = $this->legacyRow('type', $this->type);
        }
        $rows[] = $this->legacyRow('name', $this->name);
        if ($this->typeId !== null) {
            $rows[] = $this->legacyRow('blockId', ($this->tag === 'input' ? '2_' : '1_') . $this->typeId);
        }

        $filterRows = [];
        $groupRows = [];
        foreach ($this->parameters as $key => $value) {
            if (is_string($value)) {
                $rows[] = $this->legacyRow($key, $value);
            } elseif (str_starts_with($key, 'filters_') && array_filter($value, is_array(...)) === []) {
                /** @var list<string> $value */
                $filterRows[] = $this->legacyRow('filters', '', subgrpId: 1);
                foreach ($value as $filter) {
                    $filterRows[] = $this->legacyRow(mb_substr($key, 8), $filter, grpLevel: 1, parentGrpId: 1);
                }
            } else {
                foreach ($value as $index => $fields) {
                    foreach (is_array($fields) ? $fields : [] as $field => $fieldValue) {
                        $groupRows[] = $this->legacyRow(
                            $field === '' ? $key : "{$key}__{$field}",
                            $fieldValue,
                            fieldIndex: $index
                        );
                    }
                }
            }
        }

        return [...$rows, ...$filterRows, ...$groupRows];
    }

    /**
     * @return _LegacyRow
     */
    private function legacyRow(
        string $key,
        string $value,
        int $grpLevel = 0,
        ?int $subgrpId = null,
        ?int $parentGrpId = null,
        ?int $fieldIndex = null,
    ): array {
        return [
            'config_group' => $this->tag,
            'config_group_id' => $this->id,
            'config_key' => $key,
            'config_value' => $value,
            'grp_level' => $grpLevel,
            'subgrp_id' => $subgrpId,
            'parent_grp_id' => $parentGrpId,
            'fieldIndex' => $fieldIndex,
        ];
    }
}
