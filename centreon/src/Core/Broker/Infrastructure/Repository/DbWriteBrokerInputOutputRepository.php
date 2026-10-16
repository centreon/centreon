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

use Centreon\Infrastructure\DatabaseConnection;
use Core\Broker\Application\Repository\WriteBrokerInputOutputRepositoryInterface;
use Core\Broker\Domain\Model\BrokerInputOutput;
use Core\Broker\Domain\Model\BrokerInputOutputField;
use Core\Broker\Domain\Model\NewBrokerInputOutput;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;

/**
 * @phpstan-import-type _BrokerInputOutputParameter from \Core\Broker\Domain\Model\BrokerInputOutput
 */
class DbWriteBrokerInputOutputRepository extends AbstractRepositoryRDB implements WriteBrokerInputOutputRepositoryInterface
{
    /**
     * @param DatabaseConnection $db
     */
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function add(NewBrokerInputOutput $inputOutput, int $brokerId, array $fields): int
    {
        $inputOutputId = $this->getNextOutputId($brokerId, $inputOutput->getTag());

        $this->addOutput($brokerId, $inputOutputId, $inputOutput, $fields);

        return $inputOutputId;
    }

    /**
     * @inheritDoc
     */
    public function update(BrokerInputOutput $inputOutput, int $brokerId, array $fields): void
    {
        $this->delete($brokerId, $inputOutput->getTag(), $inputOutput->getId());
        $this->addOutput($brokerId, $inputOutput->getId(), $inputOutput, $fields);
    }

    /**
     * @inheritDoc
     */
    public function delete(int $brokerId, string $tag, int $inputOutputId): void
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                DELETE FROM `:db`.`cfg_centreonbroker_input_output`
                WHERE config_id = :brokerId
                    AND input_output_id = :outputId
                    AND tag = :tag
                SQL
        ));

        $statement->bindValue(':outputId', $inputOutputId, \PDO::PARAM_INT);
        $statement->bindValue(':brokerId', $brokerId, \PDO::PARAM_INT);
        $statement->bindValue(':tag', $tag, \PDO::PARAM_STR);

        $statement->execute();
    }

    /**
     * @param int $brokerId
     * @param int $inputOutputId
     * @param BrokerInputOutput|NewBrokerInputOutput $inputOutput
     * @param array<string,BrokerInputOutputField|array<string,BrokerInputOutputField>> $fields
     *
     * @throws \Throwable
     */
    private function addOutput(
        int $brokerId,
        int $inputOutputId,
        BrokerInputOutput|NewBrokerInputOutput $inputOutput,
        array $fields,
    ): void {
        $tag = $inputOutput->getTag();
        $rows = [
            ['config_key' => 'type', 'config_value' => $inputOutput->getType()->name],
            ['config_key' => 'name', 'config_value' => $inputOutput->getName()],
            [
                'config_key' => 'blockId',
                'config_value' => ($tag === 'input' ? '2' : '1') . "_{$inputOutput->getType()->id}",
            ],
            ...$this->getParameterRows($fields, $inputOutput->getParameters()),
        ];
        $rows = array_map(
            static fn (array $row): array => [
                ...$row,
                'config_group' => $tag,
                'config_group_id' => $inputOutputId,
            ],
            $rows
        );

        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                INSERT INTO `:db`.`cfg_centreonbroker_input_output` (
                    config_id,
                    tag,
                    input_output_id,
                    name,
                    type_id,
                    type,
                    parameters
                ) VALUES (
                    :brokerId,
                    :tag,
                    :inputOutputId,
                    :name,
                    :typeId,
                    :type,
                    :parameters
                )
                SQL
        ));
        foreach (BrokerInfoRowsConverter::convert($rows) as $storedInputOutput) {
            $statement->bindValue(':brokerId', $brokerId, \PDO::PARAM_INT);
            $statement->bindValue(':tag', $storedInputOutput->tag, \PDO::PARAM_STR);
            $statement->bindValue(':inputOutputId', $storedInputOutput->id, \PDO::PARAM_INT);
            $statement->bindValue(':name', $storedInputOutput->name, \PDO::PARAM_STR);
            $statement->bindValue(':typeId', $storedInputOutput->typeId, \PDO::PARAM_INT);
            $statement->bindValue(':type', $storedInputOutput->type, \PDO::PARAM_STR);
            $statement->bindValue(':parameters', $storedInputOutput->encodeParameters(), \PDO::PARAM_STR);
            $statement->execute();
        }
    }

    /**
     * Build the parameter rows of an input/output, in the former cfg_centreonbroker_info encoding.
     *
     * @param array<string,BrokerInputOutputField|array<string,BrokerInputOutputField>> $fields expected fields informations for the input/output
     * @param _BrokerInputOutputParameter[] $values input/output parameter values
     *
     * @return list<array{config_key:string,config_value:string,subgrp_id?:int,fieldIndex?:int}>
     */
    private function getParameterRows(
        array $fields,
        array $values,
    ): array {
        $rows = [];

        foreach ($fields as $fieldName => $fieldInfo) {
            if (is_array($fieldInfo)) {
                if (($subField = current($fieldInfo)) && $subField->getType() === 'multiselect') {
                    // multiselect field
                    $composedName = "{$fieldName}_{$subField->getName()}";

                    if (
                        ! isset($values[$composedName])
                        || ! is_array($values[$composedName])
                        || $values[$composedName] === []
                    ) {
                        // multiselect values not provided, skip field.
                        continue;
                    }

                    $multiselectRows = [];
                    foreach ($values[$composedName] as $subValue) {
                        if (! is_string($subValue) || $subValue === '') {
                            // multiselect value not provied, skip value.
                            continue;
                        }

                        $multiselectRows[] = ['config_key' => $subField->getName(), 'config_value' => $subValue];
                    }

                    if ($multiselectRows === []) {
                        // no values for multiselect, skip multiselect field.
                        continue;
                    }

                    $rows = [
                        ...$rows,
                        ['config_key' => $fieldName, 'config_value' => '', 'subgrp_id' => 1],
                        ...$multiselectRows,
                    ];
                } else {
                    // grouped fields

                    if (! isset($values[$fieldName]) || ! is_array($values[$fieldName]) || $values[$fieldName] === []) {
                        // group not provided, skip grouped fields.
                        continue;
                    }

                    foreach ($values[$fieldName] as $groupIndex => $groupedValues) {
                        if (! is_array($groupedValues)) {
                            // grouped values not provided, skip group.
                            continue;
                        }

                        $groupedRows = [];
                        $empty = [];
                        foreach ($fieldInfo as $groupedFieldName => $groupFieldInfo) {
                            if (
                                ! isset($groupedValues[$groupedFieldName])
                                || ! is_string($groupedValues[$groupedFieldName])
                                || $groupedValues[$groupedFieldName] === ''
                            ) {
                                if ($groupFieldInfo->getListDefault()) {
                                    // value not provided, setting to default.
                                    $groupedValues[$groupedFieldName] = $groupFieldInfo->getListDefault();
                                } else {
                                    // value not provided, no default value, setting to empty string.
                                    $empty[] = $groupedFieldName;
                                    $groupedValues[$groupedFieldName] = '';
                                }
                            }

                            $groupedRows[] = [
                                'config_key' => "{$fieldName}__{$groupedFieldName}",
                                'config_value' => $groupedValues[$groupedFieldName],
                                'fieldIndex' => (int) $groupIndex,
                            ];
                        }

                        if (array_diff(['value', 'name'], $empty) === []) {
                            // both values 'name' and 'value' not provided or empty, skip group.
                            continue;
                        }

                        $rows = [...$rows, ...$groupedRows];
                    }
                }
            } else {
                if (! isset($values[$fieldName]) || ! is_scalar($values[$fieldName]) || $values[$fieldName] === '') {
                    if ($fieldInfo->getListDefault()) {
                        // value not provided, setting to default.
                        $values[$fieldName] = $fieldInfo->getListDefault();
                    } else {
                        $values[$fieldName] = '';
                        // value not provided, no default value, setting to empty string.
                    }
                }

                // simple field
                $rows[] = ['config_key' => $fieldName, 'config_value' => (string) $values[$fieldName]];
            }
        }

        return $rows;
    }

    private function getNextOutputId(int $brokerId, string $tag): int
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                SELECT MAX(input_output_id)
                FROM `:db`.`cfg_centreonbroker_input_output`
                WHERE config_id = :brokerId AND tag = :tag
                SQL
        ));
        $statement->bindValue(':brokerId', $brokerId, \PDO::PARAM_INT);
        $statement->bindValue(':tag', $tag, \PDO::PARAM_STR);
        $statement->execute();

        return ((int) $statement->fetchColumn()) + 1;
    }
}
