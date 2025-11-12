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
use Adaptation\Database\Connection\ValueObject\QueryParameter;

if (! isset($centreon)) {
    exit();
}

function testHostGroupDependencyExistence(?string $name = null): bool
{
    global $pearDB, $form;

    // If no name provided, consider it as non-existent
    if (empty($name)) {
        return true;
    }

    try {
        CentreonDependency::purgeObsoleteDependencies($pearDB);

        $id = $form?->getSubmitValue('dep_id');

        $query = $pearDB->createQueryBuilder()
            ->select('dep_name', 'dep_id')
            ->from('dependency')
            ->where('dep_name = :depName')
            ->getQuery();

        $params = QueryParameters::create([
            QueryParameter::string('depName', CentreonDB::escape($name))
        ]);

        $row = $pearDB->fetchAssociative($query, $params);

        if ($row === false) {
            return true;
        }

        return ((int) $row['dep_id'] === (int) $id);

    } catch (Exception $e) {
        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
            message: "Error checking host group dependency existence: {$e->getMessage()}",
            customContext: [
                'dependency_name' => $name,
                'dependency_id' => $id,
            ],
            exception: $e
        );

        return false;
    }
}

function testHostGroupDependencyCycle(array $childs = []): bool
{
    global $form;
    $parents = [];
    if (isset($form)) {
        $parents = $form->getSubmitValue('dep_hgParents');
        $childs = $form->getSubmitValue('dep_hgChilds');
        $childs = array_flip($childs);
    }
    foreach ($parents as $parent) {
        if (array_key_exists($parent, $childs)) {
            return false;
        }
    }

    return true;
}

function deleteHostGroupDependencyInDB(array $dependencies = []): void
{
    global $pearDB, $centreon;

    foreach ($dependencies as $key => $value) {
        try {
            $query = $pearDB->createQueryBuilder()
            ->select('dep_name')
            ->from('dependency')
            ->where('dep_id = :depId')
            ->limit(1)
            ->getQuery();

            $params = QueryParameters::create([
                QueryParameter::int('depId', (int) $key)
            ]);
            $row = $pearDB->fetchAssociative($query, $params);

            if (! $row) {
                continue;
            }

            $query = $pearDB->createQueryBuilder()
                ->delete('dependency')
                ->where('dep_id = :depId')
                ->getQuery();

            $pearDB->executeStatement($query, $params);

            $centreon->CentreonLogAction->insertLog('hostgroup dependency', $key, $row['dep_name'], 'd');
        } catch (Exception $e) {
            CentreonLog::create()->error(
                logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
                message: "Error deleting hostgroup dependency: {$e->getMessage()}",
                customContext: [
                    'dependency_id' => $key,
                ],
                exception: $e
            );

            continue;
        }
    }
}

function multipleHostGroupDependencyInDB(array $dependencies = [], array $nbrDup = [])
{
    global $pearDB, $centreon;

    foreach ($dependencies as $key => $value) {
        try {
            $query = $pearDB->createQueryBuilder()
                ->select('*')
                ->from('dependency')
                ->where('dep_id = :depId')
                ->limit(1)
                ->getQuery();

            $params = QueryParameters::create([
                QueryParameter::int('depId', (int) $key)
            ]);
            $row = $pearDB->fetchAssociative($query, $params);
            $row['dep_id'] = null;
            for ($i = 1; $i <= $nbrDup[$key]; $i++) {
                $val = null;
                foreach ($row as $key2 => $value2) {
                    $value2 = is_int($value2) ? (string) $value2 : $value2;
                    if ($key2 == 'dep_name') {
                        $dep_name = $value2 . '_' . $i;
                        $value2 = $value2 . '_' . $i;
                    }
                    $val
                        ? $val .= ($value2 != null ? (", '" . $value2 . "'") : ', NULL')
                        : $val .= ($value2 != null ? ("'" . $value2 . "'") : 'NULL');
                    if ($key2 != 'dep_id') {
                        $fields[$key2] = $value2;
                    }
                    if (isset($dep_name)) {
                        $fields['dep_name'] = $dep_name;
                    }
                }
                if (isset($dep_name) && testHostGroupDependencyExistence($dep_name)) {
                    $columns = ['dep_id', 'dep_name', 'dep_description', 'inherits_parent', 'execution_failure_criteria',
                        'notification_failure_criteria', 'dep_comment'];

                    $qb = $pearDB->createQueryBuilder()
                        ->insert('dependency')
                        ->values(
                             array_combine($columns,
                                explode(
                                    ', ',
                                    $val
                                )
                            )
                        )
                        ->getQuery();

                    $pearDB->executeStatement($qb);

                    $qb = $pearDB->createQueryBuilder()
                        ->select('MAX(dep_id) AS max_dep_id')
                        ->from('dependency')
                        ->getQuery();

                    $maxId = $pearDB->fetchAssociative($qb);

                    if (isset($maxId['max_dep_id'])) {
                        $qb = $pearDB->createQueryBuilder()
                            ->select('hostgroup_hg_id')
                            ->from('dependency_hostgroupParent_relation')
                            ->where('dependency_dep_id = :depId')
                            ->getQuery();
                        $params = QueryParameters::create([
                            QueryParameter::int('depId', (int) $key)
                        ]);
                        $result = $pearDB->fetchAllAssociative($qb, $params);

                        $fields['dep_hgParents'] = '';
                        foreach ($result as $hg) {
                            $qb = $pearDB->createQueryBuilder()
                                ->insert('dependency_hostgroupParent_relation')
                                ->values([
                                    'dependency_dep_id' => ':max_id',
                                    'hostgroup_hg_id' => ':hg_id'
                                ])
                                ->getQuery();
                            $params = QueryParameters::create([
                                QueryParameter::int('max_id', (int) $maxId['max_dep_id']),
                                QueryParameter::int('hg_id', (int) $hg['hostgroup_hg_id'])
                            ]);

                            $pearDB->executeStatement($qb, $params);
                            $fields['dep_hgParents'] .= $hg['hostgroup_+hg_id'] . ',';
                        }
                        $fields['dep_hgParents'] = trim($fields['dep_hgParents'], ',');

                        $qb = $pearDB->createQueryBuilder()
                            ->select('hostgroup_hg_id')
                            ->from('dependency_hostgroupChild_relation')
                            ->where('dependency_dep_id = :depId')
                            ->getQuery();
                        $params = QueryParameters::create([
                            QueryParameter::int('depId', (int) $key)
                        ]);
                        $result = $pearDB->fetchAllAssociative($qb, $params);

                        $fields['dep_hgChilds'] = '';
                        foreach ($result as $hg) {
                            $qb = $pearDB->createQueryBuilder()
                                ->insert('dependency_hostgroupChild_relation')
                                ->values([
                                    'dependency_dep_id' => ':max_id',
                                    'hostgroup_hg_id' => ':hg_id'
                                ])
                                ->getQuery();
                            $params = QueryParameters::create([
                                QueryParameter::int('max_id', (int) $maxId['max_dep_id']),
                                QueryParameter::int('hg_id', (int) $hg['hostgroup_hg_id'])
                            ]);
                            $pearDB->executeStatement($qb, $params);
                            $fields['dep_hgChilds'] .= $hg['hostgroup_hg_id'] . ',';
                        }
                        $fields['dep_hgChilds'] = trim($fields['dep_hgChilds'], ',');

                        $centreon->CentreonLogAction->insertLog(
                            'hostgroup dependency',
                            $maxId['max_dep_id'],
                            $dep_name,
                            'a',
                            $fields
                        );
                    }
                }
            }
        } catch (Exception $e) {
            CentreonLog::create()->error(
                logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
                message: "Error duplicating hostgroup dependency: {$e->getMessage()}",
                customContext: [
                    'dependency_id' => $key,
                ],
                exception: $e
            );

            continue;
        }
    }
}

function updateHostGroupDependencyInDB($dep_id = null)
{
    if (! $dep_id) {
        exit();
    }
    updateHostGroupDependency($dep_id);
    updateHostGroupDependencyHostGroupParents($dep_id);
    updateHostGroupDependencyHostGroupChilds($dep_id);
}

function insertHostGroupDependencyInDB($ret = [])
{
    $dep_id = insertHostGroupDependency($ret);
    updateHostGroupDependencyHostGroupParents($dep_id, $ret);
    updateHostGroupDependencyHostGroupChilds($dep_id, $ret);

    return $dep_id;
}

/**
 * Create a host group dependency
 *
 * @param array<string, mixed> $ret
 * @return int
 */
function insertHostGroupDependency(array $ret = []): int
{
    global $form, $pearDB, $centreon;
    if (! count($ret)) {
        $ret = $form->getSubmitValues();
    }
    try {
        $resourceValues = sanitizeResourceParameters($ret);

        $qb = $pearDB->createQueryBuilder()
            ->insert('dependency')
            ->values(
                [
                    'dep_name' => ':depName',
                    'dep_description' => ':depDescription',
                    'inherits_parent' => ':inheritsParent',
                    'execution_failure_criteria' => ':executionFailure',
                    'notification_failure_criteria' => ':notificationFailure',
                    'dep_comment' => ':depComment'
                ]
            )
            ->getQuery();
        $params = QueryParameters::create([
            QueryParameter::string('depName', $resourceValues['dep_name']),
            QueryParameter::string('depDescription', $resourceValues['dep_description']),
            QueryParameter::string('inheritsParent', $resourceValues['inherits_parent']),
            QueryParameter::string(
                'executionFailure',
                $resourceValues['execution_failure_criteria'] ?? null
            ),
            QueryParameter::string(
                'notificationFailure',
                $resourceValues['notification_failure_criteria'] ?? null
            ),
            QueryParameter::string('depComment', $resourceValues['dep_comment'])
        ]);

        $pearDB->executeStatement($qb, $params);

        $qb = $pearDB->createQueryBuilder()
            ->select('MAX(dep_id) as max_dep_id')
            ->from('dependency')
            ->getQuery();
        $result = $pearDB->fetchAssociative($qb);

        // Prepare value for changelog
        $fields = CentreonLogAction::prepareChanges($ret);

        $centreon->CentreonLogAction->insertLog(
            'hostgroup dependency',
            $result['max_dep_id'],
            $resourceValues['dep_name'],
            'a',
            $fields
        );

        return (int) $result['max_dep_id'];
    } catch (Exception $e) {
        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
            message: "Error inserting host group dependency: {$e->getMessage()}",
            customContext: $ret,
            exception: $e
        );
    }
}

/**
 * Update a host group dependency
 *
 * @param null|int $depId
 */
function updateHostGroupDependency($depId = null): void
{
    if (! $depId) {
        exit();
    }
    global $form, $pearDB, $centreon;

    try {
        $resourceValues = sanitizeResourceParameters($form->getSubmitValues());

        $qb = $pearDB->createQueryBuilder()
            ->update('dependency')
            ->set('dep_name', ':depName')
            ->set('dep_description', ':depDescription')
            ->set('inherits_parent', ':inheritsParent')
            ->set('execution_failure_criteria', ':executionFailure')
            ->set('notification_failure_criteria', ':notificationFailure')
            ->set('dep_comment', ':depComment')
            ->where('dep_id = :depId')
            ->getQuery();
        $params = QueryParameters::create([
            QueryParameter::string('depName', $resourceValues['dep_name']),
            QueryParameter::string('depDescription', $resourceValues['dep_description']),
            QueryParameter::string('inheritsParent', $resourceValues['inherits_parent']),
            QueryParameter::string(
                'executionFailure',
                $resourceValues['execution_failure_criteria'] ?? null
            ),
            QueryParameter::string(
                'notificationFailure',
                $resourceValues['notification_failure_criteria'] ?? null
            ),
            QueryParameter::string('depComment', $resourceValues['dep_comment']),
            QueryParameter::int('depId', (int) $depId)
        ]);
        $pearDB->executeStatement($qb, $params);

        // Prepare value for changelog
        $fields = CentreonLogAction::prepareChanges($resourceValues);
        $centreon->CentreonLogAction->insertLog(
            'hostgroup dependency',
            $depId,
            $resourceValues['dep_name'],
            'c',
            $fields
        );
    } catch (Exception $e) {
        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
            message: "Error updating host group dependency: {$e->getMessage()}",
            customContext: [
                'dependency_id' => $depId,
            ],
            exception: $e
        );
    }
}

/**
 * sanitize resources parameter for Create / Update a  host group dependency
 *
 * @param array<string, mixed> $resources
 * @return array<string, mixed>
 */
function sanitizeResourceParameters(array $resources): array
{
    $sanitizedParameters = [];
    $sanitizedParameters['dep_name'] = HtmlAnalyzer::sanitizeAndRemoveTags($resources['dep_name']);
    if (empty($sanitizedParameters['dep_name'])) {
        throw new InvalidArgumentException(_("Dependency name can't be empty"));
    }

    $sanitizedParameters['dep_description'] = HtmlAnalyzer::sanitizeAndRemoveTags($resources['dep_description']);
    if (empty($sanitizedParameters['dep_description'])) {
        throw new InvalidArgumentException(_("Dependency description can't be empty"));
    }

    $resources['inherits_parent']['inherits_parent'] == 1
        ? $sanitizedParameters['inherits_parent'] = '1'
        : $sanitizedParameters['inherits_parent'] = '0';

    if (isset($resources['execution_failure_criteria']) && is_array($resources['execution_failure_criteria'])) {
        $sanitizedParameters['execution_failure_criteria'] = HtmlAnalyzer::sanitizeAndRemoveTags(
            implode(
                ',',
                array_keys($resources['execution_failure_criteria'])
            )
        );
    }

    if (isset($resources['notification_failure_criteria']) && is_array($resources['notification_failure_criteria'])) {
        $sanitizedParameters['notification_failure_criteria'] = HtmlAnalyzer::sanitizeAndRemoveTags(
            implode(
                ',',
                array_keys($resources['notification_failure_criteria'])
            )
        );
    }

    $sanitizedParameters['dep_comment'] = HtmlAnalyzer::sanitizeAndRemoveTags($resources['dep_comment']);

    return $sanitizedParameters;
}

function updateHostGroupDependencyHostGroupParents($dep_id = null, array $ret = []): void
{
    if (! $dep_id) {
        exit();
    }
    global $form, $pearDB;

    $qb = $pearDB->createQueryBuilder()
        ->delete('dependency_hostgroupParent_relation')
        ->where('dependency_dep_id = :depId')
        ->getQuery();
    $params = QueryParameters::create([
        QueryParameter::int('depId', (int) $dep_id)
    ]);
    $pearDB->executeStatement($qb, $params);

    if (isset($ret['dep_hgParents'])) {
        $ret = $ret['dep_hgParents'];
    } else {
        $ret = CentreonUtils::mergeWithInitialValues($form, 'dep_hgParents');
    }
    $counter = count($ret);
    for ($i = 0; $i < $counter; $i++) {
        $qb = $pearDB->createQueryBuilder()
            ->insert('dependency_hostgroupParent_relation')
            ->values([
                'dependency_dep_id' => ':depId',
                'hostgroup_hg_id' => ':hgId'
            ])
            ->getQuery();
        $params = QueryParameters::create([
            QueryParameter::int('depId', (int) $dep_id),
            QueryParameter::int('hgId', (int) $ret[$i])
        ]);
        $pearDB->executeStatement($qb, $params);
    }
}

function updateHostGroupDependencyHostGroupChilds($dep_id = null, array $ret = []): void
{
    if (! $dep_id) {
        exit();
    }
    global $form, $pearDB;

    $qb = $pearDB->createQueryBuilder()
        ->delete('dependency_hostgroupChild_relation')
        ->where('dependency_dep_id = :depId')
        ->getQuery();
    $params = QueryParameters::create([
        QueryParameter::int('depId', (int) $dep_id)
    ]);
    $pearDB->executeStatement($qb, $params);

    if (isset($ret['dep_hgChilds'])) {
        $ret = $ret['dep_hgChilds'];
    } else {
        $ret = CentreonUtils::mergeWithInitialValues($form, 'dep_hgChilds');
    }
    $counter = count($ret);
    for ($i = 0; $i < $counter; $i++) {
        $qb = $pearDB->createQueryBuilder()
            ->insert('dependency_hostgroupChild_relation')
            ->values([
                'dependency_dep_id' => ':depId',
                'hostgroup_hg_id' => ':hgId'
            ])
            ->getQuery();
        $params = QueryParameters::create([
            QueryParameter::int('depId', (int) $dep_id),
            QueryParameter::int('hgId', (int) $ret[$i])
        ]);
        $pearDB->executeStatement($qb, $params);
    }
}
