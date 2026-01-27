<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

use Adaptation\Database\Connection\Collection\BatchInsertParameters;
use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Domain\Exception\ValueObjectException;

/**
 * @param null $name
 * @return bool
 */
function testExistence($name = null)
{
    global $pearDB, $form;
    $id = null;

    if (isset($form)) {
        $id = $form->getSubmitValue('acl_res_id');
    }
    $name = HtmlAnalyzer::sanitizeAndRemoveTags($name);
    $statement = $pearDB->prepare('SELECT acl_res_name, acl_res_id FROM `acl_resources` WHERE acl_res_name = :name');
    $statement->bindValue(':name', $name, PDO::PARAM_STR);
    $statement->execute();

    return ! (($lca = $statement->fetch()) && $lca['acl_res_id'] != $id);
}

/**
 * @param null $aclResId
 * @param array $acls
 */
function enableLCAInDB($aclResId = null, $acls = [])
{
    global $pearDB, $centreon;

    if (! $aclResId && ! count($acls)) {
        return;
    }
    if ($aclResId) {
        $acls = [$aclResId => '1'];
    }

    foreach ($acls as $key => $value) {
        $query = "UPDATE `acl_groups` SET `acl_group_changed` = '1' "
            . "WHERE acl_group_id IN (SELECT acl_group_id FROM acl_res_group_relations WHERE acl_res_id = '{$key}')";
        $pearDB->query($query);
        $query = "UPDATE `acl_resources` SET acl_res_activate = '1', `changed` = '1' "
            . "WHERE `acl_res_id` = '" . $key . "'";
        $pearDB->query($query);
        $query = "SELECT acl_res_name FROM `acl_resources` WHERE acl_res_id = '" . (int) $key . "' LIMIT 1";
        $dbResult = $pearDB->query($query);
        $row = $dbResult->fetch();
        $centreon->CentreonLogAction->insertLog('resource access', $key, $row['acl_res_name'], 'enable');
    }
}

/**
 * @param null $aclResId
 * @param array $acls
 */
function disableLCAInDB($aclResId = null, $acls = [])
{
    global $pearDB, $centreon;

    if (! $aclResId && ! count($acls)) {
        return;
    }

    if ($aclResId) {
        $acls = [$aclResId => '1'];
    }

    foreach ($acls as $key => $value) {
        $query = "UPDATE `acl_groups` SET `acl_group_changed` = '1' "
            . "WHERE acl_group_id IN (SELECT acl_group_id FROM acl_res_group_relations WHERE acl_res_id = '{$key}')";
        $pearDB->query($query);
        $query = "UPDATE `acl_resources` SET acl_res_activate = '0', `changed` = '1' "
            . "WHERE `acl_res_id` = '" . $key . "'";
        $pearDB->query($query);
        $query = "SELECT acl_res_name FROM `acl_resources` WHERE acl_res_id = '" . (int) $key . "' LIMIT 1";
        $dbResult = $pearDB->query($query);
        $row = $dbResult->fetch();
        $centreon->CentreonLogAction->insertLog('resource access', $key, $row['acl_res_name'], 'disable');
    }
}

/**
 * Delete ACL entry in DB
 * @param $acls
 */
function deleteLCAInDB($acls = [])
{
    global $pearDB, $centreon;

    foreach ($acls as $key => $value) {
        $query = "SELECT acl_res_name FROM `acl_resources` WHERE acl_res_id = '" . (int) $key . "' LIMIT 1";
        $dbResult = $pearDB->query($query);
        $row = $dbResult->fetch();
        $query = "UPDATE `acl_groups` SET `acl_group_changed` = '1' "
            . "WHERE acl_group_id IN (SELECT acl_group_id FROM acl_res_group_relations WHERE acl_res_id = '{$key}')";
        $pearDB->query($query);

        $pearDB->query("DELETE FROM `acl_resources` WHERE acl_res_id = '" . $key . "'");
        $centreon->CentreonLogAction->insertLog('resource access', $key, $row['acl_res_name'], 'd');
    }
}

/**
 * Duplicate Resources ACL
 * @param $lcas
 * @param $nbrDup
 */
function multipleLCAInDB($lcas = [], $nbrDup = [])
{
    global $pearDB, $centreon;

    foreach ($lcas as $key => $value) {
        $dbResult = $pearDB->query("SELECT * FROM `acl_resources` WHERE acl_res_id = '" . $key . "' LIMIT 1");
        $row = $dbResult->fetch();
        $row['acl_res_id'] = '';

        for ($i = 1; $i <= $nbrDup[$key]; $i++) {
            $values = [];

            foreach ($row as $key2 => $value2) {
                $value2 = is_int($value2) ? (string) $value2 : $value2;
                if ($key2 == 'acl_res_name') {
                    $acl_name = $value2 . '_' . $i;
                    $value2 = $value2 . '_' . $i;
                }
                $values[] = $value2 != null
                    ? "'" . $value2 . "'"
                    : 'NULL';
                if ($key2 != 'acl_res_id') {
                    $fields[$key2] = $value2;
                }
                if (isset($acl_res_name)) {
                    $fields['acl_res_name'] = $acl_res_name;
                }
            }

            if (testExistence($acl_name)) {
                if ($values !== []) {
                    $pearDB->query('INSERT INTO acl_resources VALUES (' . implode(',', $values) . ')');
                }

                $dbResult = $pearDB->query('SELECT MAX(acl_res_id) FROM acl_resources');
                $maxId = $dbResult->fetch();
                $dbResult->closeCursor();

                if (isset($maxId['MAX(acl_res_id)'])) {
                    duplicateGroups($key, $maxId['MAX(acl_res_id)'], $pearDB);
                    $centreon->CentreonLogAction->insertLog(
                        'resource access',
                        $maxId['MAX(acl_res_id)'],
                        $acl_name,
                        'a',
                        $fields
                    );
                }
            }
        }
    }
}

/**
 * @param $idTD
 * @param $acl_id
 * @param $pearDB
 */
function duplicateGroups($idTD, $acl_id, $pearDB)
{
    $query = 'INSERT INTO acl_res_group_relations (acl_res_id, acl_group_id) '
        . 'SELECT :acl_id AS acl_res_id, acl_group_id FROM acl_res_group_relations WHERE acl_res_id = :acl_res_id';
    $statement = $pearDB->prepare($query);
    $statement->bindValue(':acl_id', (int) $acl_id, PDO::PARAM_INT);
    $statement->bindValue(':acl_res_id', (int) $idTD, PDO::PARAM_INT);
    $statement->execute();
    // host categories
    $query = 'INSERT INTO acl_resources_hc_relations (acl_res_id, hc_id) '
        . '(SELECT :acl_id, hc_id FROM acl_resources_hc_relations WHERE acl_res_id = :acl_res_id)';
    $statement = $pearDB->prepare($query);
    $statement->bindValue(':acl_id', (int) $acl_id, PDO::PARAM_INT);
    $statement->bindValue(':acl_res_id', (int) $idTD, PDO::PARAM_INT);
    $statement->execute();
    // hostgroups
    $query = 'INSERT INTO acl_resources_hg_relations (acl_res_id, hg_hg_id) '
        . '(SELECT :acl_id, hg_hg_id FROM acl_resources_hg_relations WHERE acl_res_id = :acl_res_id)';
    $statement = $pearDB->prepare($query);
    $statement->bindValue(':acl_id', (int) $acl_id, PDO::PARAM_INT);
    $statement->bindValue(':acl_res_id', (int) $idTD, PDO::PARAM_INT);
    $statement->execute();

    // host exceptions
    $query = 'INSERT INTO acl_resources_hostex_relations (acl_res_id, host_host_id) '
        . '(SELECT :acl_id, host_host_id FROM acl_resources_hostex_relations WHERE acl_res_id = :acl_res_id)';
    $statement = $pearDB->prepare($query);
    $statement->bindValue(':acl_id', (int) $acl_id, PDO::PARAM_INT);
    $statement->bindValue(':acl_res_id', (int) $idTD, PDO::PARAM_INT);
    $statement->execute();

    // hosts
    $query = 'INSERT INTO acl_resources_host_relations (acl_res_id, host_host_id) '
        . '(SELECT :acl_id, host_host_id FROM acl_resources_host_relations WHERE acl_res_id = :acl_res_id)';
    $statement = $pearDB->prepare($query);
    $statement->bindValue(':acl_id', (int) $acl_id, PDO::PARAM_INT);
    $statement->bindValue(':acl_res_id', (int) $idTD, PDO::PARAM_INT);
    $statement->execute();

    // meta
    $query = 'INSERT INTO acl_resources_meta_relations (acl_res_id, meta_id) '
        . '(SELECT :acl_id, meta_id FROM acl_resources_meta_relations WHERE acl_res_id = :acl_res_id)';
    $statement = $pearDB->prepare($query);
    $statement->bindValue(':acl_id', (int) $acl_id, PDO::PARAM_INT);
    $statement->bindValue(':acl_res_id', (int) $idTD, PDO::PARAM_INT);
    $statement->execute();

    // poller
    $query = 'INSERT INTO acl_resources_poller_relations (acl_res_id, poller_id) '
        . '(SELECT :acl_id, poller_id FROM acl_resources_poller_relations WHERE acl_res_id = :acl_res_id)';
    $statement = $pearDB->prepare($query);
    $statement->bindValue(':acl_id', (int) $acl_id, PDO::PARAM_INT);
    $statement->bindValue(':acl_res_id', (int) $idTD, PDO::PARAM_INT);
    $statement->execute();

    // service categories
    $query = 'INSERT INTO acl_resources_sc_relations (acl_res_id, sc_id) '
        . '(SELECT :acl_id, sc_id FROM acl_resources_sc_relations WHERE acl_res_id = :acl_res_id)';
    $statement = $pearDB->prepare($query);
    $statement->bindValue(':acl_id', (int) $acl_id, PDO::PARAM_INT);
    $statement->bindValue(':acl_res_id', (int) $idTD, PDO::PARAM_INT);
    $statement->execute();

    // service groups
    $query = 'INSERT INTO acl_resources_sg_relations (acl_res_id, sg_id) '
        . '(SELECT :acl_id, sg_id FROM acl_resources_sg_relations WHERE acl_res_id = :acl_res_id)';
    $statement = $pearDB->prepare($query);
    $statement->bindValue(':acl_id', (int) $acl_id, PDO::PARAM_INT);
    $statement->bindValue(':acl_res_id', (int) $idTD, PDO::PARAM_INT);
    $statement->execute();
}

/**
 * @param $idTD
 * @param $acl_id
 * @param $pearDB
 */
function duplicateContactGroups($idTD, $acl_id, $pearDB)
{
    $query = 'INSERT INTO acl_res_group_relations (acl_res_id, acl_group_id) '
        . "SELECT acl_res_id, '{$acl_id}' AS acl_group_id FROM acl_res_group_relations WHERE acl_group_id = '{$idTD}'";
    $pearDB->query($query);
}

/**
 * Update ACL entry
 *
 * @param null|int $aclId
 *
 * @throws RepositoryException
 * @return void
 */
function updateLCAInDB($aclId = null): void
{
    global $form, $centreon;

    if (! $aclId) {
        return;
    }

    updateLCA($aclId);
    updateGroups($aclId);
    updateHosts($aclId);
    updateHostGroups($aclId);
    updateHostexcludes($aclId);
    updateServiceCategories($aclId);
    updateHostCategories($aclId);
    updateServiceGroups($aclId);
    updateMetaServices($aclId);
    updateImageFolders($aclId);
    updatePollers($aclId);

    $submittedValues = $form->getSubmitValues();
    $fields = CentreonLogAction::prepareChanges($submittedValues);

    $centreon->CentreonLogAction->insertLog(
        'resource access',
        $aclId,
        $submittedValues['acl_res_name'],
        'c',
        $fields,
    );
}

/**
 * Insert ACL entry
 *
 * @throws RepositoryException
 * @return int
 */
function insertLCAInDB(): int
{
    global $form, $centreon;

    $aclId = insertLCA();
    updateGroups($aclId);
    updateHosts($aclId);
    updateHostGroups($aclId);
    updateHostexcludes($aclId);
    updateServiceCategories($aclId);
    updateHostCategories($aclId);
    updateServiceGroups($aclId);
    updateMetaServices($aclId);
    updateImageFolders($aclId);
    updatePollers($aclId);

    $submittedValues = $form->getSubmitValues();

    $fields = CentreonLogAction::prepareChanges($submittedValues);

    $centreon->CentreonLogAction->insertLog(
        'resource access',
        $aclId,
        $submittedValues['acl_res_name'],
        'a',
        $fields,
    );

    return $aclId;
}

/**
 * Insert LCA in DB
 *
 * @throws RepositoryException
 */
function insertLCA(): int
{
    global $form, $pearDB;

    $submittedValues = $form->getSubmitValues();
    $resourceValues = sanitizeResourceParameters($submittedValues);

    try {
        $queryParameters = QueryParameters::create(
            [
                QueryParameter::string('aclResourceName', $resourceValues['acl_res_name']),
                QueryParameter::string('aclResourceAlias', $resourceValues['acl_res_alias']),
                QueryParameter::string('allHosts', $resourceValues['all_hosts']),
                QueryParameter::string('allHostGroups', $resourceValues['all_hostgroups']),
                QueryParameter::string('allServiceGroups', $resourceValues['all_servicegroups']),
                QueryParameter::string('allImageFolders', $resourceValues['all_image_folders']),
                QueryParameter::string('aclResourceActivate', $resourceValues['acl_res_activate']),
                QueryParameter::string('aclResourceComment', $resourceValues['acl_res_comment']),
            ]
        );

        $pearDB->insert(
            <<<'SQL'
                    INSERT INTO `acl_resources`
                    (
                        acl_res_name,
                        acl_res_alias,
                        all_hosts,
                        all_hostgroups,
                        all_servicegroups,
                        all_image_folders,
                        acl_res_activate,
                        changed,
                        acl_res_comment
                    )
                    VALUES
                    (
                        :aclResourceName,
                        :aclResourceAlias,
                        :allHosts,
                        :allHostGroups,
                        :allServiceGroups,
                        :allImageFolders,
                        :aclResourceActivate,
                        1,
                        :aclResourceComment
                    )
                SQL,
            $queryParameters
        );

        return (int) $pearDB->getLastInsertId();
    } catch (ValueObjectException|CollectionException|ConnectionException $e) {
        throw new RepositoryException(
            message: 'Unable to insert ACL resource in database',
            context: ['submitted_values' => $resourceValues],
            previous: $e
        );
    }
}

/**
 * Update resource ACL in DB
 *
 * @param int|null $aclId
 *
 * @throws RepositoryException
 */
function updateLCA(?int $aclId = null): void
{
    global $form, $pearDB;

    if (! $aclId) {
        return;
    }

    $submittedValues = $form->getSubmitValues();

    $resourceValues = sanitizeResourceParameters($submittedValues);

    try {
        $pearDB->update(
            <<<'SQL'
                    UPDATE `acl_resources`
                    SET
                        acl_res_name = :aclResourceName,
                        acl_res_alias = :aclResourceAlias,
                        all_hosts = :allHosts,
                        all_hostgroups = :allHostGroups,
                        all_servicegroups = :allServiceGroups,
                        all_image_folders = :allImageFolders,
                        acl_res_activate = :aclResourceActivate,
                        acl_res_comment = :aclResourceComment,
                        changed = 1
                    WHERE acl_res_id = :aclResourceId
                SQL,
            QueryParameters::create(
                [
                    QueryParameter::string('aclResourceName', $resourceValues['acl_res_name']),
                    QueryParameter::string('aclResourceAlias', $resourceValues['acl_res_alias']),
                    QueryParameter::string('allHosts', $resourceValues['all_hosts']),
                    QueryParameter::string('allHostGroups', $resourceValues['all_hostgroups']),
                    QueryParameter::string('allServiceGroups', $resourceValues['all_servicegroups']),
                    QueryParameter::string('allImageFolders', $resourceValues['all_image_folders']),
                    QueryParameter::string('aclResourceActivate', $resourceValues['acl_res_activate']),
                    QueryParameter::string('aclResourceComment', $resourceValues['acl_res_comment']),
                    QueryParameter::int('aclResourceId', $aclId),
                ]
            )
        );
    } catch (ValueObjectException|CollectionException|ConnectionException $e) {
        throw new RepositoryException(
            message: 'Unable to update ACL resource in database',
            context: ['submitted_values' => $resourceValues],
            previous: $e
        );
    }
}

/** ****************
 *
 * @param int|null $aclId
 *
 * @throws RepositoryException
 * @return void
 */
function updateGroups($aclId = null): void
{
    global $form, $pearDB;

    if (! $aclId) {
        return;
    }

    try {
        $aclResourceId = QueryParameter::int('aclResourceId', $aclId);

        $pearDB->delete(
            'DELETE FROM acl_res_group_relations WHERE acl_res_id = :aclResourceId',
            QueryParameters::create([$aclResourceId]),
        );

        $aclGroupsSubmitted = $form->getSubmitValue('acl_groups');

        $insertParameters = [];

        if (is_array($aclGroupsSubmitted) && $aclGroupsSubmitted !== []) {
            foreach ($aclGroupsSubmitted as $index => $aclGroupId) {
                $insertParameters[] = QueryParameters::create([
                    $aclResourceId,
                    QueryParameter::int('acl_group' . $index, (int) $aclGroupId),
                ]);
            }

            $pearDB->batchInsert(
                tableName: 'acl_res_group_relations',
                columns: [
                    'acl_res_id',
                    'acl_group_id',
                ],
                batchInsertParameters: BatchInsertParameters::create($insertParameters),
            );
        }
    } catch (ValueObjectException|CollectionException|ConnectionException $e) {
        throw new RepositoryException(
            message: 'Unable to update ACL groups in database',
            context: ['acl_id' => $aclId],
            previous: $e
        );
    }
}

/**
 * @param null|int $aclId
 *
 * @throws RepositoryException
 * @return void
 */
function updateHosts($aclId = null): void
{
    global $form, $pearDB;

    if (! $aclId) {
        return;
    }

    try {
        $aclResourceId = QueryParameter::int('aclResourceId', $aclId);

        $pearDB->delete(
            'DELETE FROM acl_resources_host_relations WHERE acl_res_id = :aclResourceId',
            QueryParameters::create([$aclResourceId]),
        );

        $hostsSubmitted = $form->getSubmitValue('acl_hosts');

        $insertParameters = [];

        if (is_array($hostsSubmitted) && $hostsSubmitted !== []) {
            foreach ($hostsSubmitted as $index => $hostId) {
                $insertParameters[] = QueryParameters::create([
                    $aclResourceId,
                    QueryParameter::int('host_host_id' . $index, (int) $hostId),
                ]);
            }

            $pearDB->batchInsert(
                tableName: 'acl_resources_host_relations',
                columns: [
                    'acl_res_id',
                    'host_host_id',
                ],
                batchInsertParameters: BatchInsertParameters::create($insertParameters),
            );
        }
    } catch (ValueObjectException|CollectionException|ConnectionException $e) {
        throw new RepositoryException(
            message: 'Unable to update ACL hosts in database',
            context: ['acl_id' => $aclId],
            previous: $e
        );
    }
}

/**
 * @param int|null $aclId
 *
 * @throws RepositoryException
 * @return void
 */
function updateImageFolders($aclId = null): void
{
    global $form, $pearDB;

    if (! $aclId) {
        return;
    }

    try {
        $insertParameters = [];

        $aclResourceId = QueryParameter::int('aclResourceId', $aclId);

        $pearDB->delete(
            'DELETE FROM acl_resources_image_folder_relations WHERE acl_res_id = :aclResourceId',
            QueryParameters::create([$aclResourceId]),
        );

        $imageFoldersSubmitted = $form->getSubmitValue('acl_image_folder');

        // Find directory IDs for system directories that are dashboards, centreon-map, ppm
        $systemDirectoryIds = $pearDB->fetchFirstColumn(
            "SELECT dir_id FROM view_img_dir WHERE dir_name IN ('centreon-map', 'dashboards', 'ppm')"
        );

        foreach ($systemDirectoryIds as $systemDirectoryId) {
            $insertParameters[] = QueryParameters::create([
                $aclResourceId,
                QueryParameter::int('directory_id' . $systemDirectoryId, (int) $systemDirectoryId),
            ]);
        }

        if (is_array($imageFoldersSubmitted) && $imageFoldersSubmitted !== []) {
            foreach ($imageFoldersSubmitted as $imageFolderId) {
                $insertParameters[] = QueryParameters::create([
                    $aclResourceId,
                    QueryParameter::int('directory_id' . $imageFolderId, (int) $imageFolderId),
                ]);
            }
        }

        if ($insertParameters !== []) {
            $pearDB->batchInsert(
                tableName: 'acl_resources_image_folder_relations',
                columns: [
                    'acl_res_id',
                    'dir_id',
                ],
                batchInsertParameters: BatchInsertParameters::create($insertParameters),
            );
        }
    } catch (ValueObjectException|CollectionException|ConnectionException $e) {
        throw new RepositoryException(
            message: 'Unable to update ACL image folders in database',
            context: ['acl_id' => $aclId],
            previous: $e
        );
    }
}

/**
 * @param null|int $aclId
 *
 * @throws RepositoryException
 * @return void
 */
function updatePollers($aclId = null): void
{
    global $form, $pearDB;

    if (! $aclId) {
        return;
    }

    try {
        $aclResourceId = QueryParameter::int('aclResourceId', $aclId);

        $pearDB->delete(
            'DELETE FROM acl_resources_poller_relations WHERE acl_res_id = :aclResourceId',
            QueryParameters::create([$aclResourceId]),
        );

        $pollersSubmitted = $form->getSubmitValue('acl_pollers');

        $insertParameters = [];

        if (is_array($pollersSubmitted) && $pollersSubmitted !== []) {
            foreach ($pollersSubmitted as $index => $pollerId) {
                $insertParameters[] = QueryParameters::create([
                    $aclResourceId,
                    QueryParameter::int('poller_id' . $index, (int) $pollerId),
                ]);
            }

            $pearDB->batchInsert(
                tableName: 'acl_resources_poller_relations',
                columns: [
                    'acl_res_id',
                    'poller_id',
                ],
                batchInsertParameters: BatchInsertParameters::create($insertParameters),
            );
        }
    } catch (ValueObjectException|CollectionException|ConnectionException $e) {
        throw new RepositoryException(
            message: 'Unable to update ACL pollers in database',
            context: ['acl_id' => $aclId],
            previous: $e
        );
    }
}

/**
 * @param null|int $aclId
 *
 * @throws RepositoryException
 * @return void
 */
function updateHostexcludes($aclId = null): void
{
    global $form, $pearDB;

    if (! $aclId) {
        return;
    }

    try {
        $aclResourceId = QueryParameter::int('aclResourceId', $aclId);

        $pearDB->delete(
            'DELETE FROM acl_resources_hostex_relations WHERE acl_res_id = :aclResourceId',
            QueryParameters::create([$aclResourceId]),
        );

        $hostsToExcludeSubmitted = $form->getSubmitValue('acl_hostexclude');

        $insertParameters = [];

        if (is_array($hostsToExcludeSubmitted) && $hostsToExcludeSubmitted !== []) {
            foreach ($hostsToExcludeSubmitted as $index => $hostId) {
                $insertParameters[] = QueryParameters::create([
                    $aclResourceId,
                    QueryParameter::int('host_host_id' . $index, (int) $hostId),
                ]);
            }

            $pearDB->batchInsert(
                tableName: 'acl_resources_hostex_relations',
                columns: [
                    'acl_res_id',
                    'host_host_id',
                ],
                batchInsertParameters: BatchInsertParameters::create($insertParameters),
            );
        }
    } catch (ValueObjectException|CollectionException|ConnectionException $e) {
        throw new RepositoryException(
            message: 'Unable to update ACL host excludes in database',
            context: ['acl_id' => $aclId],
            previous: $e
        );
    }

}

/**
 * @param null|int $aclId
 *
 * @throws RepositoryException
 * @return void
 */
function updateHostGroups($aclId = null): void
{
    global $form, $pearDB;

    if (! $aclId) {
        return;
    }

    try {
        $aclResourceId = QueryParameter::int('aclResourceId', $aclId);

        $pearDB->delete(
            'DELETE FROM acl_resources_hg_relations WHERE acl_res_id = :aclResourceId',
            QueryParameters::create([$aclResourceId]),
        );

        $hostGroupsSubmitted = $form->getSubmitValue('acl_hostgroup');

        $insertParameters = [];

        if (is_array($hostGroupsSubmitted) && $hostGroupsSubmitted !== []) {
            foreach ($hostGroupsSubmitted as $index => $hostGroupId) {
                $insertParameters[] = QueryParameters::create([
                    $aclResourceId,
                    QueryParameter::int('hg_hg_id' . $index, (int) $hostGroupId),
                ]);
            }

            $pearDB->batchInsert(
                tableName: 'acl_resources_hg_relations',
                columns: [
                    'acl_res_id',
                    'hg_hg_id',
                ],
                batchInsertParameters: BatchInsertParameters::create($insertParameters),
            );
        }
    } catch (ValueObjectException|CollectionException|ConnectionException $e) {
        throw new RepositoryException(
            message: 'Unable to update ACL host groups in database',
            context: ['acl_id' => $aclId],
            previous: $e
        );
    }
}

/**
 * Update Service categories entries in DB
 *
 * @param null|int $aclId
 *
 * @throws RepositoryException
 * @return void
 */
function updateServiceCategories($aclId = null): void
{
    global $form, $pearDB;

    if (! $aclId) {
        return;
    }

    try {
        $aclResourceId = QueryParameter::int('aclResourceId', $aclId);

        $pearDB->delete(
            'DELETE FROM acl_resources_sc_relations WHERE acl_res_id = :aclResourceId',
            QueryParameters::create([$aclResourceId]),
        );

        $serviceCategoriesSubmitted = $form->getSubmitValue('acl_sc');

        $insertParameters = [];

        if (is_array($serviceCategoriesSubmitted) && $serviceCategoriesSubmitted !== []) {
            foreach ($serviceCategoriesSubmitted as $index => $serviceCategoryId) {
                $insertParameters[] = QueryParameters::create([
                    $aclResourceId,
                    QueryParameter::int('sc_id' . $index, (int) $serviceCategoryId),
                ]);
            }

            $pearDB->batchInsert(
                tableName: 'acl_resources_sc_relations',
                columns: [
                    'acl_res_id',
                    'sc_id',
                ],
                batchInsertParameters: BatchInsertParameters::create($insertParameters),
            );
        }
    } catch (ValueObjectException|CollectionException|ConnectionException $e) {
        throw new RepositoryException(
            message: 'Unable to update ACL service categories in database',
            context: ['acl_id' => $aclId],
            previous: $e
        );
    }
}

/**
 * Update HC entries in DB
 *
 * @param null|int $aclId
 *
 * @throws RepositoryException
 * @return void
 */
function updateHostCategories($aclId = null): void
{
    global $form, $pearDB;

    if (! $aclId) {
        return;
    }

    try {
        $aclResourceId = QueryParameter::int('aclResourceId', $aclId);

        $pearDB->delete(
            'DELETE FROM acl_resources_hc_relations WHERE acl_res_id = :aclResourceId',
            QueryParameters::create([$aclResourceId]),
        );

        $hostCategoriesSubmitted = $form->getSubmitValue('acl_hc');

        $insertParameters = [];

        if (is_array($hostCategoriesSubmitted) && $hostCategoriesSubmitted !== []) {
            foreach ($hostCategoriesSubmitted as $index => $hostCategoryId) {
                $insertParameters[] = QueryParameters::create([
                    $aclResourceId,
                    QueryParameter::int('hc_id' . $index, (int) $hostCategoryId),
                ]);
            }

            $pearDB->batchInsert(
                tableName: 'acl_resources_hc_relations',
                columns: [
                    'acl_res_id',
                    'hc_id',
                ],
                batchInsertParameters: BatchInsertParameters::create($insertParameters),
            );
        }
    } catch (ValueObjectException|CollectionException|ConnectionException $e) {
        throw new RepositoryException(
            message: 'Unable to update ACL host categories in database',
            context: ['acl_id' => $aclId],
            previous: $e
        );
    }
}

/**
 * Update Service groups entries in DB
 *
 * @param null|int $aclId
 *
 * @throws RepositoryException
 * @return void
 */
function updateServiceGroups($aclId = null): void
{
    global $form, $pearDB;

    if (! $aclId) {
        return;
    }

    try {
        $aclResourceId = QueryParameter::int('aclResourceId', $aclId);

        $pearDB->delete(
            'DELETE FROM acl_resources_sg_relations WHERE acl_res_id = :aclResourceId',
            QueryParameters::create([$aclResourceId]),
        );

        $serviceGroupsSubmitted = $form->getSubmitValue('acl_sg');

        $insertParameters = [];

        if (is_array($serviceGroupsSubmitted) && $serviceGroupsSubmitted !== []) {
            foreach ($serviceGroupsSubmitted as $index => $serviceGroupId) {
                $insertParameters[] = QueryParameters::create([
                    $aclResourceId,
                    QueryParameter::int('sg_id' . $index, (int) $serviceGroupId),
                ]);
            }

            $pearDB->batchInsert(
                tableName: 'acl_resources_sg_relations',
                columns: [
                    'acl_res_id',
                    'sg_id',
                ],
                batchInsertParameters: BatchInsertParameters::create($insertParameters),
            );
        }
    } catch (ValueObjectException|CollectionException|ConnectionException $e) {
        throw new RepositoryException(
            message: 'Unable to update ACL service groups in database',
            context: ['acl_id' => $aclId],
            previous: $e
        );
    }
}

/**
 * Update Meta services entries in DB
 *
 * @param null|int $aclId
 *
 * @throws RepositoryException
 * @return void
 */
function updateMetaServices($aclId = null): void
{
    global $form, $pearDB;

    if (! $aclId) {
        return;
    }

    try {
        $aclResourceId = QueryParameter::int('aclResourceId', $aclId);

        $pearDB->delete(
            'DELETE FROM acl_resources_meta_relations WHERE acl_res_id = :aclResourceId',
            QueryParameters::create([$aclResourceId]),
        );

        $metaServicesSubmitted = $form->getSubmitValue('acl_meta');

        $insertParameters = [];

        if (is_array($metaServicesSubmitted) && $metaServicesSubmitted !== []) {
            foreach ($metaServicesSubmitted as $index => $metaServiceId) {
                $insertParameters[] = QueryParameters::create([
                    $aclResourceId,
                    QueryParameter::int('meta_id' . $index, (int) $metaServiceId),
                ]);
            }

            $pearDB->batchInsert(
                tableName: 'acl_resources_meta_relations',
                columns: [
                    'acl_res_id',
                    'meta_id',
                ],
                batchInsertParameters: BatchInsertParameters::create($insertParameters),
            );
        }
    } catch (ValueObjectException|CollectionException|ConnectionException $e) {
        throw new RepositoryException(
            message: 'Unable to update ACL meta services in database',
            context: ['acl_id' => $aclId],
            previous: $e
        );
    }
}

/**
 * sanitize resources parameter for Create / Update a Resource ACL
 *
 * @param array<string, mixed> $resources
 *
 * @throws InvalidArgumentException
 * @return array<string, mixed>
 */
function sanitizeResourceParameters(array $resources): array
{
    $sanitizedParameters = [];
    $sanitizedParameters['acl_res_name'] = HtmlSanitizer::createFromString($resources['acl_res_name'])->getString();

    if (empty($sanitizedParameters['acl_res_name'])) {
        throw new InvalidArgumentException(_("ACL Resource name can't be empty"));
    }

    $sanitizedParameters['acl_res_alias'] = HtmlSanitizer::createFromString($resources['acl_res_alias'])->getString();
    $sanitizedParameters['acl_res_comment'] = HtmlSanitizer::createFromString($resources['acl_res_comment'])->getString();

    // set default value for unconsistent FILTER_VALIDATE_INT
    $default = ['options' => ['default' => 0]];
    // Cast to string as it will be inserted as an enum '0','1'
    $sanitizedParameters['all_hosts']
        = (string) filter_var($resources['all_hosts']['all_hosts'] ?? null, FILTER_VALIDATE_INT, $default);

    $sanitizedParameters['all_hostgroups']
        = (string) filter_var($resources['all_hostgroups']['all_hostgroups'] ?? null, FILTER_VALIDATE_INT, $default);

    $sanitizedParameters['all_servicegroups']
        = (string) filter_var(
            $resources['all_servicegroups']['all_servicegroups'] ?? null,
            FILTER_VALIDATE_INT,
            $default
        );

    $sanitizedParameters['all_image_folders']
        = (string) filter_var(
            $resources['all_image_folders']['all_image_folders'] ?? null,
            FILTER_VALIDATE_INT,
            $default
        );

    $sanitizedParameters['acl_res_activate']
        = (string) filter_var($resources['acl_res_activate']['acl_res_activate'] ?? null, FILTER_VALIDATE_INT, $default);

    return $sanitizedParameters;
}
