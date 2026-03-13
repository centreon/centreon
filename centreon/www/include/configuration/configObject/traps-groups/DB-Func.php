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
 * @return bool true if duplicate exists with different id, false if name is available or belongs to current record
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
        if (! $pearDB->isTransactionActive()) {
            $pearDB->startTransaction();
        }

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

        $pearDB->commitTransaction();
    } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
        try {
            if ($pearDB->isTransactionActive()) {
                $pearDB->rollBackTransaction();
            }
        } catch (ConnectionException $rollbackException) {
            throw new RepositoryException(
                'Failed to roll back transaction in deleteTrapGroupInDB: ' . $rollbackException->getMessage(),
                [
                    'trapGroups' => array_keys($trapGroups),
                ],
                $rollbackException,
            );
        }

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

    $copySql = <<<'SQL'
            INSERT INTO traps_group_relation (traps_group_id, traps_id)
            SELECT :new_group_id, traps_id
            FROM traps_group_relation
            WHERE traps_group_id = :old_group_id
        SQL;

    foreach (array_keys($trapGroups) as $trapGroupId) {
        $trapGroupId = filter_var($trapGroupId, FILTER_VALIDATE_INT);
        if ($trapGroupId === false) {
            continue;
        }

        // Fetch original row
        $originalGroup = $pearDB->fetchAssociative(
            <<<'SQL'
                    SELECT *
                    FROM traps_group
                    WHERE traps_group_id = :id
                    LIMIT 1
                SQL,
            QueryParameters::create([
                QueryParameter::create('id', (int) $trapGroupId, QueryParameterTypeEnum::INTEGER),
            ])
        );
        if (! $originalGroup) {
            continue;
        }

        $baseName = $originalGroup['traps_group_name'];
        $dupCount = filter_var($nbrDup[$trapGroupId] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
        if ($dupCount === false || $dupCount === 0) {
            continue;
        }

        unset($originalGroup['traps_group_id']);
        $columns = array_keys($originalGroup);
        $insertSql = sprintf(
            'INSERT INTO traps_group (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', array_map(fn ($col) => ':' . $col, $columns)),
        );

        $suffix = 1;
        for ($i = 0; $i < $dupCount && $suffix <= $dupCount + 1000; $suffix++) {
            $newName = $baseName . '_' . $suffix;
            if (testTrapGroupExistence($newName)) {
                continue;
            }
            $i++;

            $dup = $originalGroup;
            $dup['traps_group_name'] = $newName;

            try {
                $pearDB->startTransaction();

                $params = [];
                foreach ($columns as $col) {
                    $params[] = QueryParameter::create(
                        $col,
                        $dup[$col],
                        $dup[$col] === null ? QueryParameterTypeEnum::NULL : QueryParameterTypeEnum::STRING,
                    );
                }
                $pearDB->insert($insertSql, QueryParameters::create($params));
                $newGroupId = (int) $pearDB->getLastInsertId();
                if ($newGroupId <= 0) {
                    $pearDB->rollBackTransaction();
                    continue;
                }

                // Copy relations
                $pearDB->insert($copySql, QueryParameters::create([
                    QueryParameter::create('new_group_id', $newGroupId, QueryParameterTypeEnum::INTEGER),
                    QueryParameter::create('old_group_id', (int) $trapGroupId, QueryParameterTypeEnum::INTEGER),
                ]));

                $pearDB->commitTransaction();
            } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
                if ($pearDB->isTransactionActive()) {
                    $pearDB->rollBackTransaction();
                }

                throw new RepositoryException(
                    'Error while duplicating trap group',
                    ['trapGroupId' => $trapGroupId, 'newName' => $newName],
                    $exception,
                );
            }

            $oreon->CentreonLogAction->insertLog(
                'traps_group',
                $newGroupId,
                $newName,
                'a',
                CentreonLogAction::prepareChanges($dup),
            );
        }
        if ($i < $dupCount) {
            error_log("Could only create {$i}/{$dupCount} duplicates for trap group '{$baseName}' ({$trapGroupId}): suffix search exhausted");
        }
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

    if ($ret === []) {
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
