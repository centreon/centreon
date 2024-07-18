<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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
                DELETE FROM cfg_centreonbroker_info
                WHERE config_id = :brokerId
                    AND config_group_id = :outputId
                    AND config_group = :tag
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
        array $fields
    ): void
    {
        $query = <<<'SQL'
            INSERT INTO `:db`.`cfg_centreonbroker_info` (
                config_id,
                config_key,
                config_value,
                config_group,
                config_group_id,
                grp_level,
                subgrp_id,
                parent_grp_id,
                fieldIndex
            ) VALUES
            SQL;

        $inserts = [];
        $inserts[] = <<<'SQL'
            (:brokerId, 'type', :typeName, :tag, :inputOutputId, 0, null, null, null)
            SQL;
        $inserts[] = <<<'SQL'
            (:brokerId, 'name', :name, :tag, :inputOutputId, 0, null, null, null)
            SQL;
        $inserts[] = <<<'SQL'
            (:brokerId, 'blockId', :blockId, :tag, :inputOutputId, 0, null, null, null)
            SQL;

        [$paramInserts, $bindValues] = $this->getInsertQueries($fields, $inputOutput->getParameters());
        $inserts = [...$inserts, ...$paramInserts];

        $request = $query . ' ' . implode(',', $inserts);
        $statement = $this->db->prepare($this->translateDbName($request));

        $statement->bindValue(':inputOutputId', $inputOutputId, \PDO::PARAM_INT);
        $statement->bindValue(':brokerId', $brokerId, \PDO::PARAM_INT);
        $statement->bindValue(':typeName', $inputOutput->getType()->name, \PDO::PARAM_STR);
        $statement->bindValue(':tag', $inputOutput->getTag(), \PDO::PARAM_STR);
        $statement->bindValue(':name', $inputOutput->getName(), \PDO::PARAM_STR);
        $blockId = ($inputOutput->getTag() === 'input' ? '2' : '1') . "_{$inputOutput->getType()->id}";
        $statement->bindValue(':blockId', $blockId, \PDO::PARAM_STR);
        foreach ($bindValues as $key => $value) {
            if (str_starts_with($key, ':key_') || str_starts_with($key, ':value_')) {
                $statement->bindValue($key, $value, \PDO::PARAM_STR);
            } else {
                $statement->bindValue($key, $value, \PDO::PARAM_INT);
            }
        }

        $statement->execute();
    }

    /**
     * Build the insert elements of the add input/output query.
     * Return an array containing :
     * - an array of the query elements
     * - an array of all the values to bind.
     *
     * @param array<string,BrokerInputOutputField|array<string,BrokerInputOutputField>> $fields expected fields informations for the input/output
     * @param _BrokerInputOutputParameter[] $values input/output parameter values
     *
     * @return array{string[],array<int|string|null>}
     */
    private function getInsertQueries(
        array $fields,
        array $values
    ): array {
        $inserts = [];
        $bindValues = [];

        $index = 0;
        foreach ($fields as $fieldName => $fieldInfo) {
            if (is_array($fieldInfo)) {
                if (($subField = current($fieldInfo)) && $subField->getType() === 'multiselect') {
                    // multiselect field
                    $composedName = "{$fieldName}_{$subField->getName()}";

                    if (
                        ! isset($values[$composedName])
                        || ! is_array($values[$composedName])
                        || [] === $values[$composedName]
                    ) {
                        // multiselect values not provided, skip field.
                        continue;
                    }

                    $multiselectInserts = [];
                    $multiselectBindValues = [];

                    $multiselectInserts[] = <<<SQL
                        (:brokerId, :key_{$index}, :value_{$index}, :tag, :inputOutputId, 0, 1, null, null)
                        SQL;

                    $multiselectBindValues[":key_{$index}"] = $fieldName;
                    $multiselectBindValues[":value_{$index}"] = '';

                    foreach ($values[$composedName] as $subIndex => $subValue) {
                        if (! is_string($subValue) || '' === $subValue) {
                            // multiselect value not provied, skip value.
                            continue;
                        }

                        $composedIndex = "{$index}_{$subIndex}";
                        $multiselectInserts[] = <<<SQL
                            (
                                :brokerId,
                                :key_{$composedIndex},
                                :value_{$composedIndex},
                                :tag,
                                :inputOutputId,
                                1,
                                null,
                                1,
                                null
                            )
                            SQL;

                        $multiselectBindValues[":key_{$composedIndex}"] = $subField->getName();
                        $multiselectBindValues[":value_{$composedIndex}"] = $subValue;
                    }

                    if (count($multiselectInserts) <= 1) {
                        // no values for multiselect, skip multiselect field.
                        continue;
                    }

                    $inserts = [...$inserts, ...$multiselectInserts];
                    $bindValues = [...$bindValues, ...$multiselectBindValues];
                } else {
                    // grouped fields

                    if (! isset($values[$fieldName]) || ! is_array($values[$fieldName]) || [] === $values[$fieldName]) {
                        // group not provided, skip grouped fields.
                        continue;
                    }

                    foreach ($values[$fieldName] as $groupIndex => $groupedValues) {
                        if (! is_array($groupedValues)) {
                            // grouped values not provided, skip group.
                            continue;
                        }

                        $groupedInserts = [];
                        $groupedBindValues = [];
                        $empty = [];
                        $groupedFieldIndex = 0;
                        foreach ($fieldInfo as $groupedFieldName => $groupFieldInfo) {
                            if (
                                ! isset($groupedValues[$groupedFieldName])
                                || ! is_string($groupedValues[$groupedFieldName])
                                || '' === $groupedValues[$groupedFieldName]
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

                            $composedIndex = "{$index}_{$groupIndex}_{$groupedFieldIndex}";
                            $groupedInserts[] = <<<SQL
                                (
                                    :brokerId,
                                    :key_{$composedIndex},
                                    :value_{$composedIndex},
                                    :tag,
                                    :inputOutputId,
                                    0,
                                    null,
                                    null,
                                    :fieldIndex_{$composedIndex}
                                )
                                SQL;

                            $groupedBindValues[":key_{$composedIndex}"] = "{$fieldName}__{$groupedFieldName}";
                            $groupedBindValues[":value_{$composedIndex}"] = $groupedValues[$groupedFieldName];
                            $groupedBindValues[":fieldIndex_{$composedIndex}"] = $groupIndex;

                            ++$groupedFieldIndex;
                        }

                        if ([] === array_diff(['value', 'name'], $empty)) {
                            // both values 'name' and 'value' not provided or empty, skip group.
                            continue;
                        }

                        $inserts = [...$inserts, ...$groupedInserts];
                        $bindValues = [...$bindValues, ...$groupedBindValues];
                    }
                }
            } else {
                if (! isset($values[$fieldName]) || ! is_scalar($values[$fieldName]) || '' === $values[$fieldName]) {
                    if ($fieldInfo->getListDefault()) {
                        // value not provided, setting to default.
                        $values[$fieldName] = $fieldInfo->getListDefault();
                    } else {
                        $values[$fieldName] = '';
                        // value not provided, no default value, setting to empty string.
                    }
                }

                // simple field
                $inserts[] = <<<SQL
                    (:brokerId, :key_{$index}, :value_{$index}, :tag, :inputOutputId, 0, null, null, null)
                    SQL;

                $bindValues[":key_{$index}"] = $fieldName;
                $bindValues[":value_{$index}"] = $values[$fieldName];
            }

            ++$index;
        }

        return [$inserts, $bindValues];
    }

    private function getNextOutputId(int $brokerId, string $tag): int
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                SELECT MAX(config_group_id)
                FROM `:db`.`cfg_centreonbroker_info`
                WHERE config_id = :brokerId && config_group = :tag
                SQL
        ));
        $statement->bindValue(':brokerId', $brokerId, \PDO::PARAM_INT);
        $statement->bindValue(':tag', $tag, \PDO::PARAM_STR);
        $statement->execute();

       return ((int) $statement->fetchColumn()) + 1;
    }
}
