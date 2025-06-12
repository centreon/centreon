<?php
/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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
 */
declare(strict_types=1);

use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Domain\Exception\ValueObjectException;
use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Adaptation\Database\Connection\Exception\ConnectionException;

if (!isset($centreon)) {
    exit();
}

function testHostDependencyExistence(?string $name): bool
{
    global $pearDB, $form;

    try {
        CentreonDependency::purgeObsoleteDependencies($pearDB);

        $sql = 'SELECT dep_id FROM dependency WHERE dep_name = :name';

        $params = QueryParameters::create([
            QueryParameter::string('name', (string) $name)
        ]);

        $row = $pearDB->fetchAssociative($sql, $params);
    } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
        throw new RepositoryException(
            'Error testing host dependency existence',
            ['dep_name' => $name],
            $exception
        );
    }

    $currentId = $form?->getSubmitValue('dep_id');
    if ($row) {
        return ((int) $row['dep_id'] === (int) $currentId);
    }

    return true;
}

function testHostDependencyCycle($childs = null): bool
{
    global $form;
    $parents = [];
    $childsMap = [];

    if (isset($form)) {
        $parents = $form->getSubmitValue('dep_hostParents') ?? [];
        $childs = $form->getSubmitValue('dep_hostChilds')  ?? [];
        $childsMap = array_flip(array_map('intval', $childs));
    }

    foreach ($parents as $parent) {
        if (isset($childsMap[(int) $parent])) {
            return false;
        }
    }

    return true;
}

function deleteHostDependencyInDB(array $dependencies = []): void
{
    global $pearDB, $centreon;

    foreach ($dependencies as $depId => $_) {
        try {
            // fetch name
            $sqlSel = 'SELECT dep_name FROM dependency WHERE dep_id = :id LIMIT 1';
            $params = QueryParameters::create([
                QueryParameter::int('id', (int) $depId),
            ]);
            $row = $pearDB->fetchAssociative($sqlSel, $params);
            if (! $row) {
                // nothing to delete
                continue;
            }
            // delete record
            $sqlDel = 'DELETE FROM dependency WHERE dep_id = :id';
            $pearDB->delete($sqlDel, $params);

            // log deletion
            $centreon->CentreonLogAction->insertLog(
                'host dependency',
                (int) $depId,
                $row['dep_name'] ?? '',
                'd'
            );
        } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
            throw new RepositoryException(
                'Error deleting host dependency',
                ['dep_id' => $depId],
                $exception
            );
        }
    }
}

function multipleHostDependencyInDB(array $dependencies = [], array $nbrDup = []): void
{
    global $pearDB, $centreon;

    foreach ($dependencies as $depId => $_) {
        try {
            $pearDB->beginTransaction();
            // fetch original dependency row
            $sqlSel = 'SELECT * FROM dependency WHERE dep_id = :id LIMIT 1';
            $params = QueryParameters::create([
                QueryParameter::int('id', (int) $depId),
            ]);
            $original = $pearDB->fetchAssociative($sqlSel, $params);
            if (!$original) {
                continue;
            }
            unset($original['dep_id']);

            // duplicate as many times as requested
            $count = $nbrDup[$depId] ?? 0;
            for ($i = 1; $i <= $count; $i++) {
                $dup = $original;
                $dupName = $dup['dep_name'] . "_$i";
                $dup['dep_name'] = HtmlSanitizer::createFromString($dupName)
                    ->removeTags()
                    ->sanitize()
                    ->getString();

                if (! testHostDependencyExistence($dup['dep_name'])) {
                    continue;
                }

                // insert duplicated dependency
                $cols   = array_keys($dup);
                $sqlIns = sprintf(
                    'INSERT INTO dependency (%s) VALUES (%s)',
                    implode(', ', $cols),
                    implode(', ', array_map(fn($c) => ':' . $c, $cols))
                );
                $insParams = QueryParameters::create(
                    array_map(fn($c) => QueryParameter::string($c, (string) $dup[$c]), $cols)
                );
                $pearDB->insert($sqlIns, $insParams);
                $newId = (int) $pearDB->getLastInsertId();

                // duplicate relations
                foreach ([
                    'dependency_hostParent_relation',
                    'dependency_hostChild_relation',
                    'dependency_serviceChild_relation'
                ] as $table) {
                    // No need of Distinct here, as we are sure that the relation is unique
                    $sqlRelSel = "SELECT * FROM $table WHERE dependency_dep_id = :id";
                    $rels = $pearDB->fetchAllAssociative($sqlRelSel, $params);

                    foreach ($rels as $rowRel) {
                        $fields   = array_keys($rowRel);
                        $sqlRelIns = sprintf(
                            'INSERT INTO %s (%s) VALUES (%s)',
                            $table,
                            implode(', ', $fields),
                            implode(', ', array_map(fn($c) => ':' . $c, $fields))
                        );
                        $relParams = QueryParameters::create(
                            array_map(fn($c) =>
                                QueryParameter::int(
                                    $c,
                                    $c === 'dependency_dep_id' ? $newId : (int) $rowRel[$c]
                                ),
                                $fields
                            )
                        );
                        $pearDB->insert($sqlRelIns, $relParams);
                    }
                }
                $centreon->CentreonLogAction->insertLog(
                    "host dependency",
                    $newId,
                    $dupName,
                    "a",
                    $fields
                );

            }
            $pearDB->commit();
        } catch (ValueObjectException|CollectionException|ConnectionException|RepositoryException $exception) {
            $pearDB->rollBack();
            throw new RepositoryException(
                'Error duplicating host dependency',
                ['dep_id' => $depId],
                $exception
            );
        }
    }
}

/**
 * Create a host dependency
 *
 * @param array<string, mixed> $ret
 * @return int
 */
function insertHostDependency(array $data = []): int
{
    global $form, $pearDB, $centreon;

    try {
        $values = sanitizeResourceParameters($data ?: $form->getSubmitValues());

        $ins = <<<SQL
                INSERT INTO dependency (
                    dep_name,
                    dep_description,
                    inherits_parent,
                    execution_failure_criteria,
                    notification_failure_criteria,
                    dep_comment
                ) VALUES (
                    :depName,
                    :depDesc,
                    :inherits,
                    :exeFail,
                    :notFail,
                    :comment
                )
            SQL;

        $params = QueryParameters::create([
            QueryParameter::string('depName', $values['dep_name']),
            QueryParameter::string('depDesc', $values['dep_description']),
            QueryParameter::string('inherits', $values['inherits_parent']),
            QueryParameter::string('exeFail', $values['execution_failure_criteria'] ?? null),
            QueryParameter::string('notFail', $values['notification_failure_criteria'] ?? null),
            QueryParameter::string('comment', $values['dep_comment']),
        ]);

        $pearDB->insert($ins, $params);
        $id = (int) $pearDB->getLastInsertId();

        $centreon->CentreonLogAction->insertLog(
            "host dependency",
            $id,
            $values['dep_name'],
            'a',
            CentreonLogAction::prepareChanges($values)
        );

        return $id;
    } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
        throw new RepositoryException(
            'Error inserting host dependency',
            ['data' => $data],
            $exception
        );
    }
}

/**
 * Update a host dependency
 *
 * @param null|int $depId
 */
function updateHostDependency(int $depId): void
{
    global $form, $pearDB, $centreon;

    try {
        $values = sanitizeResourceParameters($form->getSubmitValues());

        $update = <<<SQL
                UPDATE dependency SET
                dep_name = :depName,
                dep_description = :depDesc,
                inherits_parent = :inherits,
                execution_failure_criteria = :exeFail,
                notification_failure_criteria = :notFail,
                dep_comment = :comment
                WHERE dep_id = :depId
            SQL;

        $params = QueryParameters::create([
            QueryParameter::string('depName', $values['dep_name']),
            QueryParameter::string('depDesc', $values['dep_description']),
            QueryParameter::string('inherits', $values['inherits_parent']),
            QueryParameter::string('exeFail', $values['execution_failure_criteria'] ?? null),
            QueryParameter::string('notFail', $values['notification_failure_criteria'] ?? null),
            QueryParameter::string('comment', $values['dep_comment']),
            QueryParameter::int('depId', $depId),
        ]);

        $pearDB->update($update, $params);

        $centreon->CentreonLogAction->insertLog(
            'host dependency',
            $depId,
            $values['dep_name'] ?? '',
            'c',
            CentreonLogAction::prepareChanges($values)
        );
    } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
        throw new RepositoryException(
            'Error updating host dependency',
            ['dep_id' => $depId],
            $exception
        );
    }
}

/**
 * sanitize resources parameter for Create / Update a host dependency
 *
 * @param array<string, mixed> $resources
 * @return array<string, mixed>
 */
function sanitizeResourceParameters(array $resources): array
{
    $sanitizedParameters = [];

    $sanitizedParameters['dep_name'] = HtmlSanitizer::createFromString(($resources['dep_name'] ?? ''))
        ->removeTags()->sanitize()->getString();
    if ($sanitizedParameters['dep_name'] === '') {
        throw new RepositoryException(_("Dependency name can't be empty"));
    }

    $sanitizedParameters['dep_description'] = HtmlSanitizer::createFromString(($resources['dep_description'] ?? ''))
        ->removeTags()->sanitize()->getString();
    if ($sanitizedParameters['dep_description'] === '') {
        throw new RepositoryException(_("Dependency description can't be empty"));
    }

    $sanitizedParameters['inherits_parent'] = $resources['inherits_parent']['inherits_parent'] == 1 ? '1' : '0';

    if (!empty($resources['execution_failure_criteria']) && is_array($resources['execution_failure_criteria'])) {
        $sanitizedParameters['execution_failure_criteria'] = HtmlSanitizer::createFromString(
            implode(',', array_keys($resources['execution_failure_criteria']))
        )->removeTags()->sanitize()->getString();
    }

    if (!empty($resources['notification_failure_criteria']) && is_array($resources['notification_failure_criteria'])) {
        $sanitizedParameters['notification_failure_criteria'] = HtmlSanitizer::createFromString(
            implode(',', array_keys($resources['notification_failure_criteria']))
        )->removeTags()->sanitize()->getString();
    }

    $sanitizedParameters['dep_comment'] = HtmlSanitizer::createFromString(($resources['dep_comment'] ?? ''))
        ->removeTags()->sanitize()->getString();

    return $sanitizedParameters;
}

function updateHostDependencyHostParents(int $depId, array $list = []): void
{
    global $pearDB, $form;

    try {
        $del = 'DELETE FROM dependency_hostParent_relation WHERE dependency_dep_id = :id';
        $pearDB->delete($del, QueryParameters::create([ QueryParameter::int('id', $depId) ]));

        $items = isset($list["dep_hostParents"]) ? $list["dep_hostParents"] : CentreonUtils::mergeWithInitialValues($form, 'dep_hostParents');
        foreach ($items as $host) {
            $ins = <<<SQL
                    INSERT INTO dependency_hostParent_relation
                    (dependency_dep_id, host_host_id)
                    VALUES (:id, :host)
                SQL;
            $pearDB->insert($ins, QueryParameters::create([
                QueryParameter::int('id', $depId),
                QueryParameter::int('host', (int) $host)
            ]));
        }
    } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
        throw new RepositoryException(
            'Error updating host dependency parents',
            ['dep_id' => $depId],
            $exception
        );
    }
}

function updateHostDependencyHostChilds(int $depId, array $list = []): void
{
    global $form, $pearDB;

    try {
        $del = 'DELETE FROM dependency_hostChild_relation WHERE dependency_dep_id = :id';
        $pearDB->delete($del, QueryParameters::create([ QueryParameter::int('id', $depId) ]));

        $items = isset($list["dep_hostChilds"]) ? $list["dep_hostChilds"] : CentreonUtils::mergeWithInitialValues($form, 'dep_hostChilds');
        foreach ($items as $host) {
            $ins = <<<SQL
                    INSERT INTO dependency_hostChild_relation
                    (dependency_dep_id, host_host_id) VALUES (:id, :host)
                SQL;
            $pearDB->insert($ins, QueryParameters::create([
                QueryParameter::int('id', $depId),
                QueryParameter::int('host', (int) $host)
            ]));
        }
    } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
        throw new RepositoryException(
            'Error updating host dependency childs',
            ['dep_id' => $depId],
            $exception
        );
    }
}

function updateHostDependencyServiceChildren(int $dep_id, array $list = []): void
{
    global $form, $pearDB;

    try {
        $del = 'DELETE FROM dependency_serviceChild_relation WHERE dependency_dep_id = :id';
        $pearDB->delete($del, QueryParameters::create([ QueryParameter::int('id', $dep_id) ]));

        $items = isset($list["dep_hSvChi"]) ? $list["dep_hSvChi"] : CentreonUtils::mergeWithInitialValues($form, 'dep_hSvChi');
        foreach ($items as $item) {
            [$host, $service] = explode('-', $item) + [null, null];
            if ($host !== null && $service !== null) {
                $ins = <<<SQL
                        INSERT INTO dependency_serviceChild_relation
                        (dependency_dep_id, service_service_id, host_host_id)
                        VALUES (:id, :service, :host)
                    SQL;
                $pearDB->insert($ins, QueryParameters::create([
                    QueryParameter::int('id', $dep_id),
                    QueryParameter::int('service', (int) $service),
                    QueryParameter::int('host', (int) $host)
                ]));
            }
        }
    } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
        throw new RepositoryException(
            'Error updating host dependency service children',
            ['dep_id' => $dep_id],
            $exception
        );
    }
}

function insertHostDependencyInDB(array $data = []): int
{
    $id = insertHostDependency($data);
    updateHostDependencyHostParents($id, $data);
    updateHostDependencyHostChilds($id, $data);
    updateHostDependencyServiceChildren($id, $data);
    return $id;
}

function updateHostDependencyInDB(int $id): void
{
    updateHostDependency($id);
    updateHostDependencyHostParents($id);
    updateHostDependencyHostChilds($id);
    updateHostDependencyServiceChildren($id);
}