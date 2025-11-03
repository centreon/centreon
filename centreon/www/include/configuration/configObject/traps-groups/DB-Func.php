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

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\Enum\QueryParameterTypeEnum;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Domain\Exception\ValueObjectException;

/**
 * Check if a trap group name already exists in DB
 *
 * @param string|null $name
 * @throws RepositoryException
 * @return bool true if allowed, false if duplicate with different id
 */
function testTrapGroupExistence(?string $name = null): bool
{
    global $pearDB, $form;

    $currentId = null;
    if (isset($form)) {
        $currentId = $form->getSubmitValue('id');
    }

    try {
        $sql = <<<'SQL'
                SELECT traps_group_id AS id
                FROM traps_group
                WHERE traps_group_name = :name
                LIMIT 1
            SQL;

        $params = QueryParameters::create([
            QueryParameter::create('name', (string) $name, QueryParameterTypeEnum::STRING),
        ]);

        $trapGroup = $pearDB->fetchAssociative($sql, $params);

        return $trapGroup !== false && ((int) $trapGroup['id'] !== (int) $currentId);
    } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
        throw new RepositoryException(
            'Error while executing testTrapGroupExistence',
            [
                'name' => $name,
                'currentId' => $currentId,
            ],
            $exception,
        );
    }
}

/**
 * Delete one or many trap groups
 *
 * @param array<int|string,mixed> $trapGroups Keys are trap_group_id
 * @throws RepositoryException
 * @return void
 */
function deleteTrapGroupInDB(array $trapGroups = []): void
{
    global $pearDB, $oreon;

    try {
        foreach (array_keys($trapGroups) as $trapGroupId) {
            $selectQuery = <<<'SQL'
                    SELECT traps_group_name AS name
                    FROM traps_group
                    WHERE traps_group_id = :id
                    LIMIT 1
                SQL;

            $params = QueryParameters::create([
                QueryParameter::create('id', (int) $trapGroupId, QueryParameterTypeEnum::INTEGER),
            ]);

            $row = $pearDB->fetchAssociative($selectQuery, $params);
            $groupName = $row['name'] ?? '';

            $pearDB->delete(
                <<<'SQL'
                        DELETE FROM traps_group
                        WHERE traps_group_id = :id
                    SQL,
                $params,
            );

            $pearDB->delete(
                <<<'SQL'
                        DELETE FROM traps_group_relation
                        WHERE traps_group_id = :id
                    SQL,
                $params,
            );

            $oreon->CentreonLogAction->insertLog(
                'traps_group',
                $trapGroupId,
                $groupName,
                'd',
            );
        }
    } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
        throw new RepositoryException(
            'Error while executing deleteTrapGroupInDB',
            [
                'trapGroups' => array_keys($trapGroups),
            ],
            $exception
        );
    }
}

/**
 * Duplicate one or more trap groups $nbrDup[$id] times
 *
 * @param array<int|string,mixed> $trapGroups
 * @param array<int|string,int> $nbrDup
 * @throws RepositoryException
 * @return void
 */
function multipleTrapGroupInDB(array $trapGroups = [], array $nbrDup = []): void
{
    global $pearDB, $oreon;

    try {
        if (! $pearDB->isTransactionActive()) {
            $pearDB->startTransaction();
        }

        foreach (array_keys($trapGroups) as $trapGroupId) {
            // Fetch original row
            $paramsId = QueryParameters::create([
                QueryParameter::create('id', (int) $trapGroupId, QueryParameterTypeEnum::INTEGER),
            ]);

            $originalGroup = $pearDB->fetchAssociative(
                <<<'SQL'
                        SELECT *
                        FROM traps_group
                        WHERE traps_group_id = :id
                        LIMIT 1
                    SQL,
                $paramsId
            );
            if (! $originalGroup) {
                // nothing to duplicate for this id
                continue;
            }

            $baseName = $originalGroup['traps_group_name'];
            $dupCount = (int) ($nbrDup[$trapGroupId] ?? 0);

            $parameters = [];
            $valuePlaceholders = [];
            $insertLogsInfo = [];
            for ($i = 1; $i <= $dupCount; $i++) {
                $newName = $baseName . '_' . $i;
                if (testTrapGroupExistence($newName)) {
                    continue;
                }

                $paramName = 'name_' . $i;
                $parameters[] = QueryParameter::create(
                    $paramName,
                    $newName,
                    QueryParameterTypeEnum::STRING,
                );
                $valuePlaceholders[] = '(:' . $paramName . ')';
                $insertLogsInfo[] = [$trapGroupId, $newName];
            }

            if ($parameters === []) {
                continue;
            }
            $insertSql = sprintf(
                'INSERT INTO traps_group (traps_group_name) VALUES %s',
                implode(', ', $valuePlaceholders),
            );
            $pearDB->insert($insertSql, QueryParameters::create($parameters));

            $firstInsertedId = (int) $pearDB->getLastInsertId();
            $totalInserted = count($parameters);
            $newIds = range($firstInsertedId, $firstInsertedId + $totalInserted - 1);

            // Map new IDs to their names
            $newGroups = [];
            foreach ($parameters as $index => $param) {
                $newGroups[$newIds[$index]] = $param->getValue();
            }

            // Copy relations
            foreach ($newGroups as $newGroupId => $newName) {
                $copyParams = QueryParameters::create([
                    QueryParameter::create('new_group_id', $newGroupId, QueryParameterTypeEnum::INTEGER),
                    QueryParameter::create('old_group_id', (int) $trapGroupId, QueryParameterTypeEnum::INTEGER),
                ]);

                $pearDB->insert(
                    <<<'SQL'
                            INSERT INTO traps_group_relation (traps_group_id, traps_id)
                            SELECT :new_group_id, traps_id
                            FROM traps_group_relation
                            WHERE traps_group_id = :old_group_id
                        SQL,
                    $copyParams,
                );
            }

            // Log insertions after successful creation
            foreach ($newGroups as $newGroupId => $newName) {
                $oreon->CentreonLogAction->insertLog(
                    'traps_group',
                    $newGroupId,
                    $newName,
                    'a',
                    [
                        'traps_group_name' => $newName,
                        'name' => $newName,
                    ]
                );
            }
        }

        $pearDB->commitTransaction();
    } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
        try {
            if ($pearDB->isTransactionActive()) {
                $pearDB->rollBackTransaction();
            }
        } catch (ConnectionException $rollbackException) {
            throw new RepositoryException(
                'Failed to roll back transaction in multipleTrapGroupInDB: ' . $rollbackException->getMessage(),
                [
                    'trapGroups' => array_keys($trapGroups),
                    'nbrDup' => $nbrDup,
                ],
                $rollbackException,
            );
        }

        throw new RepositoryException(
            'Error while executing multipleTrapGroupInDB',
            [
                'trapGroups' => array_keys($trapGroups),
                'nbrDup' => $nbrDup,
            ],
            $exception,
        );
    }
}

/**
 * Public wrapper kept for BC.
 *
 * @param int|null $id
 * @throws RepositoryException
 * @return void
 */
function updateTrapGroupInDB(?int $id = null): void
{
    if ($id === null) {
        return;
    }

    updateTrapGroup($id);
}

/**
 * Update trap group main data + its relations.
 *
 * @param int|null $id
 * @throws RepositoryException
 * @return void
 */
function updateTrapGroup(?int $id = null): void
{
    global $form, $pearDB, $oreon;

    if ($id === null) {
        return;
    }

    $ret = $form->getSubmitValues();
    $name = $ret['name'] ?? '';
    if ($name === '') {
        throw new RepositoryException(
            'Trap group name cannot be empty',
            ['ret' => $ret],
        );
    }

    try {
        $updateParams = QueryParameters::create([
            QueryParameter::create('name', $name, QueryParameterTypeEnum::STRING),
            QueryParameter::create('id', (int) $id, QueryParameterTypeEnum::INTEGER),
        ]);

        if (! $pearDB->isTransactionActive()) {
            $pearDB->startTransaction();
        }

        $pearDB->update(
            <<<'SQL'
                    UPDATE traps_group
                    SET traps_group_name = :name
                    WHERE traps_group_id = :id
                SQL,
            $updateParams,
        );

        $deleteRelParams = QueryParameters::create([
            QueryParameter::create('id', (int) $id, QueryParameterTypeEnum::INTEGER),
        ]);

        $pearDB->delete(
            <<<'SQL'
                    DELETE FROM traps_group_relation
                    WHERE traps_group_id = :id
                SQL,
            $deleteRelParams,
        );

        if (! empty($ret['traps']) && is_array($ret['traps'])) {
            foreach ($ret['traps'] as $trapId) {
                $relParams = QueryParameters::create([
                    QueryParameter::create('group_id', (int) $id, QueryParameterTypeEnum::INTEGER),
                    QueryParameter::create('trap_id', (int) $trapId, QueryParameterTypeEnum::INTEGER),
                ]);

                $pearDB->insert(
                    <<<'SQL'
                            INSERT INTO traps_group_relation (traps_group_id, traps_id)
                            VALUES (:group_id, :trap_id)
                        SQL,
                    $relParams,
                );
            }
        }

        $fields = CentreonLogAction::prepareChanges($ret);
        $oreon->CentreonLogAction->insertLog(
            'traps_group',
            $id,
            $fields['name'],
            'c',
            $fields,
        );

        $pearDB->commitTransaction();
    } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
        try {
            if ($pearDB->isTransactionActive()) {
                $pearDB->rollBackTransaction();
            }
        } catch (ConnectionException $rollbackException) {
            throw new RepositoryException(
                'Failed to roll back transaction in updateTrapGroup: ' . $rollbackException->getMessage(),
                [
                    'id' => $id,
                    'name' => $name,
                ],
                $rollbackException,
            );
        }

        throw new RepositoryException(
            'Error while executing updateTrapGroup',
            [
                'id' => $id,
                'name' => $name,
            ],
            $exception,
        );
    }
}

/**
 * Public wrapper kept for BC with legacy calls.
 *
 * @param array<string,mixed> $ret
 * @throws RepositoryException
 * @return int|null
 */
function insertTrapGroupInDB(array $ret = []): ?int
{
    return insertTrapGroup($ret);
}

/**
 * Insert a new trap group and its relations.
 *
 * @param array<string,mixed> $ret
 * @throws RepositoryException
 * @return int|null Newly created traps_group_id or null if failed
 */
function insertTrapGroup(array $ret = []): ?int
{
    global $form, $pearDB, $oreon;

    if ($ret === [] || $ret === null) {
        $ret = $form->getSubmitValues();
    }

    $name = $ret['name'] ?? '';
    if ($name === '') {
        throw new RepositoryException(
            'Trap group name cannot be empty',
            ['ret' => $ret],
        );
    }

    try {
        if (! $pearDB->isTransactionActive()) {
            $pearDB->startTransaction();
        }
        $pearDB->insert(
            <<<'SQL'
                    INSERT INTO traps_group (traps_group_name)
                    VALUES (:name)
                SQL,
            QueryParameters::create([
                QueryParameter::create('name', $name, QueryParameterTypeEnum::STRING),
            ])
        );

        $newGroupId = (int) $pearDB->getLastInsertId();

        if (! empty($ret['traps']) && is_array($ret['traps'])) {
            foreach ($ret['traps'] as $trapId) {
                $relParams = QueryParameters::create([
                    QueryParameter::create('group_id', $newGroupId, QueryParameterTypeEnum::INTEGER),
                    QueryParameter::create('trap_id', (int) $trapId, QueryParameterTypeEnum::INTEGER),
                ]);

                $pearDB->insert(
                    <<<'SQL'
                            INSERT INTO traps_group_relation (traps_group_id, traps_id)
                            VALUES (:group_id, :trap_id)
                        SQL,
                    $relParams
                );
            }
        }

        $fields = CentreonLogAction::prepareChanges($ret);
        $oreon->CentreonLogAction->insertLog(
            'traps_group',
            $newGroupId,
            $fields['name'],
            'a',
            $fields
        );

        $pearDB->commitTransaction();

        return $newGroupId;
    } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
        try {
            if ($pearDB->isTransactionActive()) {
                $pearDB->rollBackTransaction();
            }
        } catch (ConnectionException $rollbackException) {
            throw new RepositoryException(
                'Failed to roll back transaction in insertTrapGroup: ' . $rollbackException->getMessage(),
                [
                    'name' => $name,
                ],
                $rollbackException,
            );
        }

        throw new RepositoryException(
            'Error while executing insertTrapGroup',
            [
                'name' => $name,
            ],
            $exception
        );
    }
}
