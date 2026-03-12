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
use Core\Broker\Application\Repository\ReadBrokerInputOutputRepositoryInterface;
use Core\Broker\Domain\Model\BrokerInputOutput;
use Core\Broker\Domain\Model\BrokerInputOutputField;
use Core\Broker\Domain\Model\Type;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;

/**
 * @phpstan-type _InputOutputRow array{
 *     id: int,
 *     config_id: int,
 *     tag: string,
 *     type_id: int,
 *     type_name: string,
 *     name: string,
 *     parameters: string
 * }
 */
class DbReadBrokerInputOutputRepository extends AbstractRepositoryRDB implements ReadBrokerInputOutputRepositoryInterface
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
    public function findParametersByType(int $typeId): array
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                SELECT
                    field.cb_field_id,
                    field.fieldname,
                    field.fieldtype,
                    field.cb_fieldgroup_id,
                    rel.is_required,
                    grp.groupname,
                    grp.multiple,
                    list.list_default,
                    list.list_values
                FROM `:db`.`cb_field` field
                INNER JOIN `:db`.`cb_type_field_relation` rel
                    ON rel.cb_field_id = field.cb_field_id
                LEFT JOIN `:db`.`cb_fieldgroup` grp
                    ON grp.cb_fieldgroup_id = field.cb_fieldgroup_id
                LEFT JOIN (
                    SELECT
                        list.cb_field_id,
                        list.default_value as list_default,
                        GROUP_CONCAT(list_val.value_value) as list_values
                    FROM `:db`.`cb_list` as list
                    INNER JOIN `:db`.`cb_list_values` list_val
                        ON list.cb_list_id = list_val.cb_list_id
                    GROUP BY list.cb_field_id, list.cb_list_id
                ) AS list
                    ON list.cb_field_id = field.cb_field_id
                WHERE cb_type_id = :typeId
                SQL
        ));
        $statement->bindValue(':typeId', $typeId, \PDO::PARAM_INT);
        $statement->execute();
        $statement->setFetchMode(\PDO::FETCH_ASSOC);

        $groupedParameters = [];
        $simpleParameters = [];
        /**
         * @var array{
         *      cb_field_id:int,
         *      fieldname:string,
         *      fieldtype: string,
         *      cb_fieldgroup_id: null|int,
         *      is_required: int,
         *      groupname: null|string,
         *      multiple: null|int,
         *      list_default: null|string,
         *      list_values: null|string
         * } $result
         */
        foreach ($statement as $result) {
            $field = new BrokerInputOutputField(
                id: $result['cb_field_id'],
                name: $result['fieldname'],
                type: $result['fieldtype'],
                groupId: $result['cb_fieldgroup_id'],
                groupName: $result['groupname'],
                isRequired: (bool) $result['is_required'],
                isMultiple: (bool) $result['multiple'],
                listDefault: $result['list_default'],
                listValues: $result['list_values'] ? explode(',', $result['list_values']) : [],
            );

            if ($result['groupname'] !== null) {
                $groupedParameters[$result['groupname']][$result['fieldname']] = $field;
            } else {
                $simpleParameters[$result['fieldname']] = $field;
            }
        }

        return [...$simpleParameters, ...$groupedParameters];
    }

    /**
     * @inheritDoc
     */
    public function findType(string $tag, int $typeId): ?Type
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                SELECT
                    type.cb_type_id as id,
                    type.type_shortname as name
                FROM `:db`.`cb_type` `type`
                INNER JOIN `:db`.`cb_tag_type_relation` rel
                    ON type.cb_type_id = rel.cb_type_id
                INNER JOIN `:db`.`cb_tag` tag
                    ON tag.cb_tag_id = rel.cb_tag_id
                WHERE tag.tagname = :tag AND type.cb_type_id = :typeId
                SQL
        ));
        $statement->bindValue(':typeId', $typeId, \PDO::PARAM_INT);
        $statement->bindValue(':tag', $tag, \PDO::PARAM_STR);
        $statement->execute();

        if (! ($result = $statement->fetch(\PDO::FETCH_ASSOC))) {
            return null;
        }

        /** @var array{id:int,name:string} $result */
        return new Type($result['id'], $result['name']);
    }

    /**
     * @inheritDoc
     */
    public function findByIdAndBrokerId(string $tag, int $inputOutputId, int $brokerId): ?BrokerInputOutput
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                SELECT id, config_id, tag, type_id, type_name, name, parameters
                FROM `:db`.`cfg_broker_input_output`
                WHERE id = :id AND config_id = :brokerId AND tag = :tag
                SQL
        ));
        $statement->bindValue(':id', $inputOutputId, \PDO::PARAM_INT);
        $statement->bindValue(':brokerId', $brokerId, \PDO::PARAM_INT);
        $statement->bindValue(':tag', $tag, \PDO::PARAM_STR);
        $statement->execute();

        if (! ($row = $statement->fetch(\PDO::FETCH_ASSOC))) {
            return null;
        }

        /** @var _InputOutputRow $row */
        return $this->createFromRow($row);
    }

    /**
     * @inheritDoc
     */
    public function findVaultPathByBrokerId(int $brokerId): ?string
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                SELECT parameters
                FROM `:db`.`cfg_broker_input_output`
                WHERE config_id = :brokerId
                    AND CAST(parameters AS CHAR) LIKE '%secret::%'
                LIMIT 1
                SQL
        ));
        $statement->bindValue(':brokerId', $brokerId, \PDO::PARAM_INT);
        $statement->execute();

        $result = $statement->fetchColumn();
        if ($result === false) {
            return null;
        }

        /** @var array<mixed> $params */
        $params = json_decode((string) $result, true, 512, JSON_THROW_ON_ERROR);

        return $this->findVaultPathInValue($params);
    }

    /**
     * @inheritDoc
     */
    public function findAll(): array
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                SELECT id, config_id, tag, type_id, type_name, name, parameters
                FROM `:db`.`cfg_broker_input_output`
                SQL
        ));
        $statement->execute();

        $results = [];
        while (($row = $statement->fetch(\PDO::FETCH_ASSOC)) !== false) {
            /** @var _InputOutputRow $row */
            $inputOutput = $this->createFromRow($row);
            if ($inputOutput !== null) {
                $results[(int) $row['config_id']][] = $inputOutput;
            }
        }

        return $results;
    }

    /**
     * @param _InputOutputRow $row
     */
    private function createFromRow(array $row): ?BrokerInputOutput
    {
        try {
            /** @var array<mixed> $parameters */
            $parameters = json_decode($row['parameters'], true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return new BrokerInputOutput(
            id: (int) $row['id'],
            tag: $row['tag'],
            type: new Type((int) $row['type_id'], $row['type_name']),
            name: $row['name'],
            parameters: $parameters,
        );
    }

    /**
     * Recursively searches a decoded JSON value for the first string starting with 'secret::'.
     *
     * @param mixed $value
     */
    private function findVaultPathInValue(mixed $value): ?string
    {
        if (is_string($value) && str_starts_with($value, 'secret::')) {
            return $value;
        }
        if (is_array($value)) {
            foreach ($value as $item) {
                $path = $this->findVaultPathInValue($item);
                if ($path !== null) {
                    return $path;
                }
            }
        }

        return null;
    }
}
