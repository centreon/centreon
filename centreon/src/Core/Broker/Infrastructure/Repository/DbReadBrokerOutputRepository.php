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
use Core\Broker\Application\Repository\ReadBrokerOutputRepositoryInterface;
use Core\Broker\Domain\Model\BrokerOutput;
use Core\Broker\Domain\Model\BrokerOutputField;
use Core\Broker\Domain\Model\Type;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;

/**
 * @phpstan-type _Output array{
 *      config_group_id:int,
 *      config_key:string,
 *      config_value:string,
 *      subgrp_id:null|int,
 *      parent_grp_id:null|int,
 *      fieldIndex:null|int
 * }
 */
class DbReadBrokerOutputRepository extends AbstractRepositoryRDB implements ReadBrokerOutputRepositoryInterface
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
            $outputField = new BrokerOutputField(
                id: $result['cb_field_id'],
                name: $result['fieldname'],
                type: $result['fieldtype'],
                groupId: $result['cb_fieldgroup_id'],
                groupName: $result['groupname'],
                isRequired: (bool) $result['is_required'],
                isMultiple: (bool) $result['multiple'], // TODO not useful ?
                listDefault: $result['list_default'],
                listValues: $result['list_values'] ? explode(',', $result['list_values']) : [],
            );

            if ($result['groupname'] !== null) {
                $groupedParameters[$result['groupname']][$result['fieldname']] = $outputField;
            } else {
                $simpleParameters[$result['fieldname']] = $outputField;
            }
        }

        return [...$simpleParameters, ...$groupedParameters];
    }

    /**
     * @inheritDoc
     */
    public function findType(int $typeId): ?Type
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
                WHERE tag.tagname = 'output' AND type.cb_type_id = :typeId
                SQL
        ));
        $statement->bindValue(':typeId', $typeId, \PDO::PARAM_INT);
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
    public function findByIdAndBrokerId(int $outputId, int $brokerId): ?BrokerOutput
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                SELECT
                    cfg.config_group_id,
                    cfg.config_key,
                    cfg.config_value,
                    cfg.subgrp_id,
                    cfg.parent_grp_id,
                    cfg.fieldIndex
                FROM `:db`.`cfg_centreonbroker_info` cfg
                WHERE cfg.config_group = 'output'
                    AND cfg.config_id = :brokerId
                    AND cfg.config_group_id = :outputId
                SQL
        ));
        $statement->bindValue(':brokerId', $brokerId, \PDO::PARAM_INT);
        $statement->bindValue(':outputId', $outputId, \PDO::PARAM_INT);
        $statement->execute();

        if (! ($result = $statement->fetchAll(\PDO::FETCH_ASSOC))) {
            return null;
        }

        /** @var _Output[] $result */
        return $this->createFromArray($result);
    }

    /**
     * @param _Output[] $result
     *
     * @return BrokerOutput|null
     */
    private function createFromArray(array $result): ?BrokerOutput
    {
        $parameters = [];
        $groupedFields = [];

        foreach ($result as $row) {
            $id ??= $row['config_group_id'];

            if ($row['config_key'] === 'name') {
                $outputName = $row['config_value'];

                continue;
            }
            if ($row['config_key'] === 'blockId') {
                $typeId = (int) str_replace('1_', '', $row['config_value']);

                continue;
            }
            if ($row['config_key'] === 'type') {
                $typeName = $row['config_value'];

                continue;
            }
            if ($row['fieldIndex'] !== null) {
                // is part of a group field
                $grpNames = explode('__', $row['config_key']);
                $groupedFields[$grpNames[0]] = 1;
                $parameters[$grpNames[0]][$row['fieldIndex']][$grpNames[1]] = $row['config_value'];

                continue;
            }
            if ($row['subgrp_id'] !== null) {
                // is part of a multiselect
                $multiselectName = $row['config_key'];

                continue;
            }
            if ($row['parent_grp_id'] !== null) {
                // is part of a multiselect
                continue;
            }

            $parameters[$row['config_key']] = $row['config_value'];
        }

        // regrouping multiselect
        if (isset($multiselectName)) {
            foreach ($result as $row) {
                if ($row['parent_grp_id'] !== null) {
                    $parameters["{$multiselectName}_{$row['config_key']}"][] = $row['config_value'];
                }
            }
        }

        // removing password values
        foreach (array_keys($groupedFields) as $groupedFieldName) {
            $parameters[$groupedFieldName] = array_map(
                $this->removePasswordValue(...),
                array_values($parameters[$groupedFieldName])
            );
        }

        // for phpstan, should never happen
        if (! isset($id, $typeId, $typeName, $outputName)) {
            return null;
        }

        return new BrokerOutput(
            $id,
            new Type($typeId, $typeName),
            $outputName,
            $parameters
        );
    }

    /**
     * @param array{type?:string,value?:string} $groupedField
     *
     * @return array<string,int|string|null>
     */
    private function removePasswordValue(array $groupedField): array
    {
if (isset($groupedField['value'], $groupedField['type']) && $groupedField['type'] === 'password') {
            $groupedField['value'] = null;
        }

        return $groupedField;
    }
}
