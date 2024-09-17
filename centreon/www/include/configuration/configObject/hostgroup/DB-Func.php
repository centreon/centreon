<?php

/*
 * Copyright 2005-2024 Centreon
 * Centreon is developed by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

if (!isset($centreon)) {
    exit();
}

function getHGParents($hg_id, $parentList, $pearDB)
{
    /*
	 * Get Parent Groups
	 */
    $statement = $pearDB->prepre("SELECT hg_parent_id FROM hostgroup_hg_relation WHERE hg_child_id = :hg_child_id");
    $statement->bindValue(':hg_child_id', (int) $hg_id, \PDO::PARAM_INT);
    $statement->execute();
    while (($hgs = $statement->fetch(\PDO::FETCH_ASSOC)) !== false) {
        $parentList[$hgs["hg_parent_id"]] = $hgs["hg_parent_id"];
        $parentList = getHGParents($hgs["hg_parent_id"], $parentList, $pearDB);
    }
    $statement->closeCursor();
    unset($hgs);
    return $parentList;
}

function testHostGroupExistence($name = null)
{
    global $pearDB, $form, $centreon;
    $id = null;
    if (isset($form)) {
        $id = $form->getSubmitValue('hg_id');
    }

    $query = "SELECT hg_name, hg_id FROM hostgroup WHERE hg_name = '" .
        CentreonDB::escape($centreon->checkIllegalChar($name)) . "'";
    $dbResult = $pearDB->query($query);
    $hg = $dbResult->fetch();
    #Modif case
    if ($dbResult->rowCount() >= 1 && $hg["hg_id"] == $id) {
        return true;
    } #Duplicate entry
    elseif ($dbResult->rowCount() >= 1 && $hg["hg_id"] != $id) {
        return false;
    } else {
        return true;
    }
}

function enableHostGroupInDB($hg_id = null, $hg_arr = array())
{
    global $pearDB, $centreon;

    if (!$hg_id && !count($hg_arr)) {
        return;
    }

    if ($hg_id) {
        $hg_arr = [$hg_id => "1"];
    }

    $updateStatement = $pearDB->prepare("UPDATE hostgroup SET hg_activate = '1' WHERE hg_id = :hostgroupId");
    $selectStatement = $pearDB->prepare("SELECT hg_name FROM `hostgroup` WHERE `hg_id` = :hostgroupId LIMIT 1");
    foreach (array_keys($hg_arr) as $hostgroupId) {
        $updateStatement->bindValue(':hostgroupId', $hostgroupId, \PDO::PARAM_INT);
        $updateStatement->execute();

        $selectStatement->bindValue(':hostgroupId', $hostgroupId, \PDO::PARAM_INT);
        $selectStatement->execute();
        $hostgroupName = $selectStatement->fetchColumn();

        signalConfigurationChange('hostgroup', $hostgroupId);
        $centreon->CentreonLogAction->insertLog("hostgroup", $hostgroupId, $hostgroupName, "enable");
    }
}

function disableHostGroupInDB($hg_id = null, $hg_arr = array())
{
    global $pearDB, $centreon;

    if (!$hg_id && !count($hg_arr)) {
        return;
    }
    if ($hg_id) {
        $hg_arr = array($hg_id => "1");
    }

    $updateStatement = $pearDB->prepare("UPDATE hostgroup SET hg_activate = '0' WHERE hg_id = :hostgroupId");
    $selectStatement = $pearDB->prepare("SELECT hg_name FROM `hostgroup` WHERE `hg_id` = :hostgroupId LIMIT 1");
    foreach (array_keys($hg_arr) as $hostgroupId) {
        $updateStatement->bindValue(':hostgroupId', $hostgroupId, \PDO::PARAM_INT);
        $updateStatement->execute();

        $selectStatement->bindValue(':hostgroupId', $hostgroupId, \PDO::PARAM_INT);
        $selectStatement->execute();
        $hostgroupName = $selectStatement->fetchColumn();

        signalConfigurationChange('hostgroup', $hostgroupId, [], false);
        $centreon->CentreonLogAction->insertLog("hostgroup", $hostgroupId, $hostgroupName, "disable");
    }
}

/**
 * @param int $hgId
 */
function removeRelationLastHostgroupDependency(int $hgId): void
{
    global $pearDB;

    $query = 'SELECT count(dependency_dep_id) AS nb_dependency , dependency_dep_id AS id
              FROM dependency_hostgroupParent_relation
              WHERE dependency_dep_id = (SELECT dependency_dep_id FROM dependency_hostgroupParent_relation
                                         WHERE hostgroup_hg_id =  ' . $hgId . ')
              GROUP BY dependency_dep_id';
    $dbResult = $pearDB->query($query);
    $result = $dbResult->fetch();

    //is last parent
    if (isset($result['nb_dependency']) && $result['nb_dependency'] == 1) {
        $pearDB->query("DELETE FROM dependency WHERE dep_id = " . $result['id']);
    }
}

function deleteHostGroupInDB(bool $isCloudPlatform, array $hostGroups = [])
{
    global $pearDB, $centreon;

    foreach (array_keys($hostGroups) as $hostgroupId) {
        $previousPollerIds = getPollersForConfigChangeFlagFromHostgroupId((int) $hostgroupId);

        removeRelationLastHostgroupDependency((int) $hostgroupId);
        $rq = <<<'SQL'
            SELECT @nbr := (
                SELECT COUNT( * )
                FROM host_service_relation
                WHERE service_service_id = hsr.service_service_id
                GROUP BY service_service_id
            ) AS nbr,
                hsr.service_service_id
            FROM host_service_relation hsr
            WHERE hsr.hostgroup_hg_id = :hostgroup_id
            SQL;
        $stmt= $pearDB->prepare($rq);
        $stmt->bindValue(":hostgroup_id", (int) $hostgroupId, \PDO::PARAM_INT);
        $stmt->execute();

        $statement = $pearDB->prepare("DELETE FROM service WHERE service_id = :service_id");
        while ($row = $stmt->fetch()) {
            if ($row["nbr"] == 1) {
                $statement->bindValue(':service_id', (int) $row["service_service_id"], \PDO::PARAM_INT);
                $statement->execute();
            }
        }

        $statement = $pearDB->prepare(
            <<<'SQL'
                SELECT hg_name
                FROM hostgroup
                WHERE hg_id = :hostgroup_id
                LIMIT 1
                SQL
        );
        $statement->bindValue(':hostgroup_id', (int) $hostgroupId, \PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch();

        if ($isCloudPlatform) {
            if ($pearDB->beginTransaction()) {
                try {
                    $stmt = $pearDB->prepare(
                        <<<'SQL'
                            SELECT * FROM dataset_filters
                            INNER JOIN acl_resources_hg_relations arhr
                                ON arhr.acl_res_id = dataset_filters.acl_resource_id
                            WHERE hg_hg_id = :hostgroup_id
                            SQL
                    );
                    $stmt->bindValue(':hostgroup_id', (int) $hostgroupId, \PDO::PARAM_INT);
                    $stmt->execute();

                    while ($datasetFilter = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                        $resourceIds = explode(',', $datasetFilter['resource_ids']);
                        $updatedResourcesIds = array_filter(
                            $resourceIds,
                            fn ($id) => trim($id) !== (string) $hostgroupId
                        );
                        $resourcesIdsAsString = implode(',', $updatedResourcesIds);
                        if (! empty($resourcesIdsAsString)) {
                            $stmt = $pearDB->prepare(
                                <<<'SQL'
                                    UPDATE dataset_filters
                                    SET resource_ids = :resource_ids
                                    WHERE id = :id
                                    SQL
                            );
                            $stmt->bindValue(':resource_ids', $resourcesIdsAsString, \PDO::PARAM_STR);
                            $stmt->bindValue(':id', (int) $datasetFilter['id'], \PDO::PARAM_INT);

                            $stmt->execute();
                        } else {
                            $stmt = $pearDB->prepare(
                                <<<'SQL'
                                    DELETE FROM dataset_filters
                                    WHERE id = :id
                                    SQL
                            );
                            $stmt->bindValue(':id', (int) $datasetFilter['id'], \PDO::PARAM_INT);

                            $stmt->execute();
                        }
                    }
                    $pearDB->commit();
                } catch (\Throwable $exception) {
                    $pearDB->rollBack();
                    throw $exception;
                }
            }
        }

        $statement = $pearDB->prepare(
            <<<'SQL'
                DELETE FROM hostgroup WHERE hg_id = :hostgroup_id
                SQL
        );
        $statement->bindValue(':hostgroup_id', (int) $hostgroupId, \PDO::PARAM_INT);
        $statement->execute();

        signalConfigurationChange('hostgroup', (int) $hostgroupId, $previousPollerIds);
        $centreon->CentreonLogAction->insertLog("hostgroup", $hostgroupId, $row['hg_name'], "d");
    }
    $centreon->user->access->updateACL();
}

function multipleHostGroupInDB($hostGroups = array(), $nbrDup = array())
{
    global $pearDB, $centreon, $is_admin;

    $hgAcl = array();
    foreach ($hostGroups as $key => $value) {
        $dbResult = $pearDB->query("SELECT * FROM hostgroup WHERE hg_id = '" . $key . "' LIMIT 1");
        $row = $dbResult->fetch();
        $row["hg_id"] = null;
        for ($i = 1; $i <= $nbrDup[$key]; $i++) {
            $val = null;
            $rq = null;
            foreach ($row as $key2 => $value2) {
                $value2 = is_int($value2) ? (string) $value2 : $value2;
                $key2 == "hg_name" ? ($hg_name = $value2 = $value2 . "_" . $i) : null;
                $val
                    ? $val .= ($value2 != null ? (", '" . $pearDB->escape($value2) . "'") : ", NULL")
                    : $val .= ($value2 != null ? ("'" . $pearDB->escape($value2) . "'") : "NULL");
                if ($key2 != "hg_id") {
                    $fields[$key2] = $value2;
                }
                if (isset($fields["hg_name"])) {
                    $fields["hg_name"] = $hg_name;
                }
            }
            if (testHostGroupExistence($hg_name)) {
                $val ? $rq = "INSERT INTO hostgroup VALUES (" . $val . ")" : $rq = null;
                $pearDB->query($rq);
                $dbResult = $pearDB->query("SELECT MAX(hg_id) FROM hostgroup");
                $maxId = $dbResult->fetch();
                if (isset($maxId["MAX(hg_id)"])) {
                    $hgAcl[$maxId["MAX(hg_id)"]] = $key;
                    if (!$is_admin) {
                        $resource_list = $centreon->user->access->getResourceGroups();
                        if (count($resource_list)) {
                            $query = "INSERT INTO `acl_resources_hg_relations` (acl_res_id, hg_hg_id)
                                    VALUES (:acl_res_id, :hg_hg_id)";
                            $statement = $pearDB->prepare($query);
                            foreach ($resource_list as $res_id => $res_name) {
                                $statement->bindValue(':acl_res_id', (int) $res_id, \PDO::PARAM_INT);
                                $statement->bindValue(':hg_hg_id', (int) $maxId["MAX(hg_id)"], \PDO::PARAM_INT);
                                $statement->execute();
                            }
                            unset($resource_list);
                        }
                    }

                    $query = "SELECT DISTINCT hgr.host_host_id FROM hostgroup_relation hgr " .
                        "WHERE hgr.hostgroup_hg_id = '" . $key . "'";
                    $dbResult = $pearDB->query($query);
                    $fields["hg_hosts"] = "";
                    $query = "INSERT INTO hostgroup_relation VALUES (NULL, :hg_id, :host_host_id)";
                    $statement = $pearDB->prepare($query);
                    while ($host = $dbResult->fetch()) {
                        $statement->bindValue(':hg_id', (int) $maxId["MAX(hg_id)"], \PDO::PARAM_INT);
                        $statement->bindValue(':host_host_id', (int) $host["host_host_id"], \PDO::PARAM_INT);
                        $statement->execute();
                        $fields["hg_hosts"] .= $host["host_host_id"] . ",";
                    }
                    $fields["hg_hosts"] = trim($fields["hg_hosts"], ",");
                    $query = "SELECT DISTINCT cghgr.contactgroup_cg_id FROM contactgroup_hostgroup_relation cghgr " .
                        "WHERE cghgr.hostgroup_hg_id = '" . $key . "'";
                    $dbResult = $pearDB->query($query);
                    $query = "INSERT INTO contactgroup_hostgroup_relation
                        VALUES (NULL, :contactgroup_cg_id, :hostgroup_hg_id)";
                    $statement = $pearDB->prepare($query);
                    while ($cg = $dbResult->fetch()) {
                        $statement->bindValue(':contactgroup_cg_id', (int) $cg["contactgroup_cg_id"], \PDO::PARAM_INT);
                        $statement->bindValue(':hostgroup_hg_id', (int) $maxId["MAX(hg_id)"], \PDO::PARAM_INT);
                        $statement->execute();
                    }

                    signalConfigurationChange('hostgroup', (int) $maxId["MAX(hg_id)"]);
                    $centreon->CentreonLogAction->insertLog("hostgroup", $maxId["MAX(hg_id)"], $hg_name, "a", $fields);
                }
            }
        }
    }
    CentreonACL::duplicateHgAcl($hgAcl);
    $centreon->user->access->updateACL();
}

function insertHostGroupInDBForCloud(array $submittedValues = []): int
{
    global $pearDB, $centreon;

    $submittedValues['hg_name'] = $centreon->checkIllegalChar($submittedValues['hg_name']);

    $statement = $pearDB->prepare(
        'INSERT INTO hostgroup (hg_name, hg_alias, geo_coords) VALUES (:hg_name, :hg_alias, :geo_coords)'
    );

    $statement->bindValue(
        ':hg_name',
        ! empty($submittedValues['hg_name']) ? $pearDB->escape($submittedValues['hg_name']) : null,
        \PDO::PARAM_STR
    );

    $statement->bindValue(
        ':hg_alias',
        ! empty($submittedValues['hg_alias']) ? $pearDB->escape($submittedValues['hg_alias']) : null,
        \PDO::PARAM_STR
    );

    $statement->bindValue(
        ':geo_coords',
        ! empty($submittedValues['geo_coords']) ? $pearDB->escape($submittedValues['geo_coords']) : null,
        \PDO::PARAM_STR
    );

    $statement->execute();

    $statement = $pearDB->query('SELECT MAX(hg_id) FROM hostgroup');
    $record = $statement->fetch(\PDO::FETCH_ASSOC);

    $centreon->CentreonLogAction->insertLog(
        object_type: 'hostgroup',
        object_id: $record['MAX(hg_id)'],
        object_name: CentreonDB::escape($submittedValues['hg_name']),
        action_type: 'a',
        fields: CentreonLogAction::prepareChanges($submittedValues)
    );

    return ($record["MAX(hg_id)"]);
}

function insertHostGroupInDBForOnPrem(array $submittedValues = []): int
{
    global $pearDB, $centreon;

    $submittedValues['hg_name'] = $centreon->checkIllegalChar($submittedValues['hg_name']);

    $request = <<<'SQL'
        INSERT INTO hostgroup
        (
            hg_name,
            hg_alias,
            hg_notes,
            hg_notes_url,
            hg_action_url,
            hg_icon_image,
            hg_map_icon_image,
            hg_rrd_retention,
            hg_comment,
            geo_coords,
            hg_activate
        ) VALUES
        (
            :name,
            :alias,
            :notes,
            :notesUrl,
            :actionUrl,
            :iconImage,
            :mapIconImage,
            :rrdRetention,
            :comment,
            :geoCoords,
            :isActivated
        )
    SQL;

    $statement = $pearDB->prepare($request);

    $statement->bindValue(
        ':name',
        ! empty($submittedValues['hg_name']) ? $pearDB->escape($submittedValues['hg_name']) : null,
        \PDO::PARAM_STR
    );

    $statement->bindValue(
        ':alias',
        ! empty($submittedValues['hg_alias']) ? $pearDB->escape($submittedValues['hg_alias']) : null,
        \PDO::PARAM_STR
    );

    $statement->bindValue(
        ':notes',
        ! empty($submittedValues['hg_notes']) ? $pearDB->escape($submittedValues['hg_notes']) : null,
        \PDO::PARAM_STR
    );

    $statement->bindValue(
        ':notesUrl',
        ! empty($submittedValues['hg_notes_url']) ? $pearDB->escape($submittedValues['hg_notes_url']) : null,
        \PDO::PARAM_STR
    );

    $statement->bindValue(
        ':actionUrl',
        ! empty($submittedValues['hg_action_url']) ? $pearDB->escape($submittedValues['hg_action_url']) : null,
        \PDO::PARAM_STR
    );

    $statement->bindValue(
        ':iconImage',
        ! empty($submittedValues['hg_icon_image']) ? $pearDB->escape($submittedValues['hg_icon_image']) : null,
        \PDO::PARAM_STR
    );

    $statement->bindValue(
        ':mapIconImage',
        ! empty($submittedValues['hg_map_icon_image']) ? $pearDB->escape($submittedValues['hg_map_icon_image']) : null,
        \PDO::PARAM_STR
    );

    $statement->bindValue(
        ':rrdRetention',
        ! empty($submittedValues['hg_rrd_retention']) ? $pearDB->escape($submittedValues['hg_rrd_retention']) : null,
        \PDO::PARAM_STR
    );

    $statement->bindValue(
        ':comment',
        ! empty($submittedValues['hg_comment']) ? $pearDB->escape($submittedValues['hg_comment']) : null,
        \PDO::PARAM_STR
    );

    $statement->bindValue(
        ':geoCoords',
        ! empty($submittedValues['geo_coords']) ? $pearDB->escape($submittedValues['geo_coords']) : null,
        \PDO::PARAM_STR
    );

    $statement->bindValue(
        ':isActivated',
        isset($submittedValues['hg_activate']['hg_activate']) && $submittedValues['hg_activate']['hg_activate']
            ? $submittedValues['hg_activate']['hg_activate']
            : '0',
        \PDO::PARAM_STR
    );

    $statement->execute();

    $statement = $pearDB->query('SELECT MAX(hg_id) FROM hostgroup');
    $record = $statement->fetch(\PDO::FETCH_ASSOC);

    $centreon->CentreonLogAction->insertLog(
        object_type: 'hostgroup',
        object_id: $record['MAX(hg_id)'],
        object_name: CentreonDB::escape($submittedValues['hg_name']),
        action_type: 'a',
        fields: CentreonLogAction::prepareChanges($submittedValues)
    );

    return ($record["MAX(hg_id)"]);
}

function insertHostGroup(array $submittedValues, bool $isCloudPlatform): int
{
    return $isCloudPlatform
        ? insertHostGroupInDBForCloud($submittedValues)
        : insertHostGroupInDBForOnPrem($submittedValues);
}

/**
 * @param array $submittedValues
 * @param bool $isCloudPlatform
 */
function insertHostGroupInDB(bool $isCloudPlatform, array $submittedValues = []): int
{
    global $centreon, $form;

    $submittedValues = $submittedValues ?: $form->getSubmitValues();

    $hostGroupId = insertHostGroup($submittedValues, $isCloudPlatform);
    updateHostGroupHosts($hostGroupId, $submittedValues);
    updateHostgroupAcl($hostGroupId, $isCloudPlatform, $submittedValues);
    signalConfigurationChange('hostgroup', $hostGroupId);
    $centreon->user->access->updateACL();

    return $hostGroupId;
}

function updateHostGroupAcl(int $hostGroupId, bool $isCloudPlatform, $submittedValues = [])
{
    global $centreon, $pearDB;

    if ($isCloudPlatform) {
        $ruleIds = $submittedValues['resource_access_rules'];

        foreach ($ruleIds as $ruleId) {
            // get datasets configured and linked dataset filters
            $datasets = findDatasetsByRuleId($ruleId);

            /**
             * see if at least a dataset filter saved is of type hostgroup
             * if so then add the new hostgroup to the dataset
             * otherwise create a new dataset for this hostgroup
             */
            $hostGroupDatasetFilters = array_filter(
                $datasets,
                fn (array $dataset) => $dataset['dataset_filter_type'] === 'hostgroup'
            );

            // No dataset_filter of type hostgroup found. Create a new one
            if ($hostGroupDatasetFilters === []) {
                // get the dataset with the highest ID (last one added) which is the first element of the datasets array
                $lastDatasetAdded = $datasets[0];

                preg_match('/dataset_for_rule_\d+_(\d+)/', $lastDatasetAdded['dataset_name'], $matches);

                // calculate the new dataset_name
                $newDatasetName = 'dataset_for_rule_' . $ruleId . '_' . ((int) $matches[1] + 1);

                if ($pearDB->beginTransaction()) {
                    try {
                        $datasetId = createNewDataset(datasetName: $newDatasetName);
                        linkDatasetToRule(datasetId: $datasetId, ruleId: $ruleId);
                        linkHostGroupToDataset(datasetId: $datasetId, hostGroupId: $hostGroupId);
                        createNewDatasetFilter(datasetId: $datasetId, ruleId: $ruleId, hostGroupId: $hostGroupId);
                        $pearDB->commit();
                    } catch (\Throwable $exception) {
                        $pearDB->rollBack();
                        throw $exception;
                    }
                }
            } else {
                $datasetFilterToPopulate = null;
                foreach ($hostGroupDatasetFilters as $hostGroupDatasetFilter) {
                    $childDatasetFilter = array_filter(
                        $datasets,
                        fn (array $dataset) =>
                            $dataset['dataset_filter_parent_id'] === $hostGroupDatasetFilter['dataset_filter_id']
                    );

                    $parentDatasetFilter = array_filter(
                        $datasets,
                        fn (array $dataset) =>
                            $dataset['dataset_filter_id'] === $hostGroupDatasetFilter['dataset_filter_parent_id']
                    );

                    if ($childDatasetFilter !== [] || $parentDatasetFilter !== []) {
                        continue;
                    }

                    $datasetFilterToPopulate = $hostGroupDatasetFilter;
                }

                if ($datasetFilterToPopulate === null) {
                    $lastDatasetAdded = end($datasets);

                    preg_match('/dataset_for_rule_\d+_(\d+)/', $lastDatasetAdded['dataset_name'], $matches);

                    // calculate the new dataset_name
                    $newDatasetName = 'dataset_for_rule_' . $ruleId . '_' . ((int) $matches[1] + 1);

                    if ($pearDB->beginTransaction()) {
                        try {
                            $datasetId = createNewDataset(datasetName: $newDatasetName);
                            linkDatasetToRule(datasetId: $datasetId, ruleId: $ruleId);
                            linkHostGroupToDataset(datasetId: $datasetId, hostGroupId: $hostGroupId);
                            createNewDatasetFilter(datasetId: $datasetId, ruleId: $ruleId, hostGroupId: $hostGroupId);
                            $pearDB->commit();
                        } catch (\Throwable $exception) {
                            $pearDB->rollBack();
                            throw $exception;
                        }
                    }
                } else {
                    if (! empty($datasetFilterToPopulate['dataset_filter_resources'])) {
                        if ($pearDB->beginTransaction()) {
                            try {
                                linkHostGroupToDataset(
                                    datasetId: $datasetFilterToPopulate['dataset_id'],
                                    hostGroupId: $hostGroupId
                                );
                                // Expend the existing hostgroup dataset_filter
                                $expendedResourceIds = $datasetFilterToPopulate['dataset_filter_resources'] . ', '
                                    . $hostGroupId;

                                updateDatasetFiltersResourceIds(
                                    datasetFilterId: $datasetFilterToPopulate['dataset_filter_id'],
                                    resourceIds: $expendedResourceIds
                                );
                                $pearDB->commit();
                            } catch (\Throwable $exception) {
                                $pearDB->rollBack();
                                throw $exception;
                            }
                        }
                    }
                }
            }
        }
    } else {
        if (! $centreon->user->admin) {
            $userResourceAccesses = $centreon->user->access->getResourceGroups();
            if ($userResourceAccesses !== []) {
                if ($pearDB->beginTransaction()) {
                    try {
                        $statement = $pearDB->prepare(<<<SQL
                            INSERT INTO `acl_resources_hg_relations` (acl_res_id, hg_hg_id)
                            VALUES (:aclResourceId, :hostGroupId)
                            SQL
                        );
                        foreach ($userResourceAccesses as $resourceAccessId => $resourceAccessName) {
                            $statement->bindValue(':aclResourceId', (int) $resourceAccessId, \PDO::PARAM_INT);
                            $statement->bindValue(':hostGroupId', (int) $hostGroupId, \PDO::PARAM_INT);
                            $statement->execute();
                        }
                        unset($userResourceAccesses);
                    } catch (\Throwable $exception) {
                        $pearDB->rollBack();
                        throw $exception;
                    }
                }
            }
        }
    }
}

/**
 * @param int $datasetFilterId
 * @param string $resourceIds
 */
function updateDatasetFiltersResourceIds(int $datasetFilterId, string $resourceIds): void
{
    global $pearDB;

    $request = <<<'SQL'
        UPDATE dataset_filters SET resource_ids = :resourceIds WHERE `id` = :datasetFilterId
    SQL;

    $statement = $pearDB->prepare($request);
    $statement->bindValue(':datasetFilterId', $datasetFilterId, \PDO::PARAM_INT);
    $statement->bindValue(':resourceIds', $resourceIds, \PDO::PARAM_STR);
    $statement->execute();
}

/**
 * @param int $ruleId
 *
 * @throws \PDOException
 *
 * @return list<array{
 *     dataset_name: string,
 *     dataset_filter_id: int,
 *     dataset_filter_parent_id: int|null,
 *     dataset_filter_type: string,
 *     dataset_filter_resources: string,
 *     dataset_id: int,
 *     rule_id: int
 * }>|array{} */
function findDatasetsByRuleId(int $ruleId): array
{
    global $pearDB;

    $request = <<<'SQL'
        SELECT
            dataset.acl_res_name AS dataset_name,
            id AS dataset_filter_id,
            parent_id AS dataset_filter_parent_id,
            type AS dataset_filter_type,
            resource_ids AS dataset_filter_resources,
            acl_resource_id AS dataset_id,
            acl_group_id AS rule_id
        FROM dataset_filters
        INNER JOIN acl_resources AS dataset
            ON dataset.acl_res_id = dataset_filters.acl_resource_id
        WHERE dataset_filters.acl_group_id = :ruleId
        ORDER BY dataset_id DESC
    SQL;

    $statement = $pearDB->prepare($request);
    $statement->bindValue(':ruleId', $ruleId, \PDO::PARAM_INT);
    $statement->execute();

    if ($record = $statement->fetchAll(\PDO::FETCH_ASSOC)) {
        return $record;
    }

    return [];
}

/**
 * @param int $datasetId
 * @param int $hostGroupId
 */
function linkHostGroupToDataset(int $datasetId, int $hostGroupId): void
{
    global $pearDB;

    $query = <<<SQL
        INSERT INTO acl_resources_hg_relations (hg_hg_id, acl_res_id) VALUES (:hostgroupId, :datasetId)
    SQL;

    $statement = $pearDB->prepare($query);
    $statement->bindValue(':datasetId', $datasetId, \PDO::PARAM_INT);
    $statement->bindValue(':hostgroupId', $hostGroupId, \PDO::PARAM_INT);
    $statement->execute();
}

/**
 * @param int $datasetId
 * @param int $ruleId
 */
function linkDatasetToRule(int $datasetId, int $ruleId): void
{
    global $pearDB;

    // link dataset to the rule
    $query = <<<SQL
        INSERT INTO acl_res_group_relations (acl_res_id, acl_group_id) VALUES (:datasetId, :ruleId)
    SQL;

    $statement = $pearDB->prepare($query);
    $statement->bindValue(':ruleId', $ruleId, \PDO::PARAM_INT);
    $statement->bindValue(':datasetId', $datasetId, \PDO::PARAM_INT);
    $statement->execute();
}

/**
 * @param string $datasetName
 * @return int
 */
function createNewDataset(string $datasetName): int
{
    global $pearDB;
    // create new dataset
    $query = <<<'SQL'
        INSERT INTO acl_resources (acl_res_name, all_hosts, all_hostgroups, all_servicegroups, acl_res_activate, changed, cloud_specific)
        VALUES (:name, '0', '0', '0', '1', 1, 1)
    SQL;

    $statement = $pearDB->prepare($query);
    $statement->bindValue(':name', $datasetName, \PDO::PARAM_STR);
    $statement->execute();

    return $pearDB->lastInsertId();
}

/**
 * @param int $datasetId
 * @param int $ruleId
 * @param int $hostGroupId
 */
function createNewDatasetFilter(int $datasetId, int $ruleId, int $hostGroupId): void
{
    global $pearDB;

    $query = <<<SQL
        INSERT INTO dataset_filters (`type`, acl_resource_id, acl_group_id, resource_ids)
        VALUES ('hostgroup', :datasetId, :ruleId, :hostgroupId)
    SQL;

    $statement = $pearDB->prepare($query);
    $statement->bindValue(':datasetId', $datasetId, \PDO::PARAM_INT);
    $statement->bindValue(':ruleId', $ruleId, \PDO::PARAM_INT);
    $statement->bindValue(':hostgroupId', $hostGroupId, \PDO::PARAM_STR);

    $statement->execute();
}

function updateHostGroupInDBForCloud(int $hostGroupId, array $submittedValues, bool $increment = false): void
{
    global $pearDB, $centreon, $form;

    $request = <<<'SQL'
        UPDATE hostgroup SET
            hg_notes = NULL,
            hg_notes_url = NULL,
            hg_action_url = NULL,
            hg_icon_image = NULL,
            hg_map_icon_image = NULL,
            hg_rrd_retention = NULL,
            hg_comment = NULL
    SQL;

    $bindValues = [];

    if ($submittedValues === []) {
        $submittedValues = $form->getSubmitValues();
    }

    if (isset($submittedValues['hg_name'])) {
        $request .= ', hg_name = :name';
        $bindValues[':name'] = [
            \PDO::PARAM_STR,
            $pearDB->escape($submittedValues['hg_name'])
        ];
    }

    if (isset($submittedValues['hg_alias'])) {
        $request .= ', hg_alias = :alias';
        $bindValues[':alias'] = [
            \PDO::PARAM_STR,
            $pearDB->escape($submittedValues['hg_alias'])
        ];
    }

    if (isset($submittedValues['geo_coords'])) {
        $request .= ', geo_coords = :geoCoords';
        $bindValues[':geoCoords'] = [
            \PDO::PARAM_STR,
            $pearDB->escape($submittedValues['geo_coords'])
        ];

    }

    $request .= ' WHERE hg_id = :hostGroupId';

    $bindValues[':hostGroupId'] = [
        \PDO::PARAM_INT,
        $hostGroupId
    ];

    $statement = $pearDB->prepare($request);

    foreach ($bindValues as $bindName => $bindParams) {
        [$bindType, $bindValue] = $bindParams;
        $statement->bindValue($bindName, $bindValue, $bindType);
    }

    $statement->execute();

    $centreon->CentreonLogAction->insertLog(
        object_type: 'hostgroup',
        object_id: $hostGroupId,
        object_name: $pearDB->escape($submittedValues['hg_name']),
        action_type: 'c',
        fields: CentreonLogAction::prepareChanges($submittedValues)
    );
}

function updateHostGroupInDBForOnPrem(int $hostGroupId, array $submittedValues, bool $increment = false): void
{
    global $pearDB, $centreon, $form;

    $request = <<<'SQL'
        UPDATE hostgroup SET
    SQL;

    $bindValues = [];

    $submittedValues = $submittedValues ?: $form->getSubmitValues();

    if (isset($submittedValues['hg_name'])) {
        $request .= ' hg_name = :name';
        $bindValues[':name'] = [
            \PDO::PARAM_STR,
            $pearDB->escape($submittedValues['hg_name'])
        ];
    }

    if (isset($submittedValues['hg_alias'])) {
        $request .= ', hg_alias = :alias';
        $bindValues[':alias'] = [
            \PDO::PARAM_STR,
            $pearDB->escape($submittedValues['hg_alias'])
        ];
    }

    if (isset($submittedValues['hg_notes'])) {
        $request .= ', hg_notes = :notes';
        $bindValues[':notes'] = [
            \PDO::PARAM_STR,
            $pearDB->escape($submittedValues['hg_notes'])
        ];
    }

    if (isset($submittedValues['hg_notes_url'])) {
        $request .= ', hg_notes_url = :notesUrl';
        $bindValues[':notesUrl'] = [
            \PDO::PARAM_STR,
            $pearDB->escape($submittedValues['hg_notes_url'])
        ];
    }

    if (isset($submittedValues['hg_action_url'])) {
        $request .= ', hg_action_url = :actionUrl';
        $bindValues[':actionUrl'] = [
            \PDO::PARAM_STR,
            $pearDB->escape($submittedValues['hg_action_url'])
        ];
    }

    if (isset($submittedValues['hg_icon_image'])) {
        $request .= ', hg_icon_image = :iconImage';
        $bindValues[':iconImage'] = [
            \PDO::PARAM_STR,
            $submittedValues['hg_icon_image'] ? $pearDB->escape($submittedValues['hg_icon_image']) : null
        ];
    }

    if (isset($submittedValues['hg_map_icon_image'])) {
        $request .= ', hg_map_icon_image = :mapIconImage';
        $bindValues[':mapIconImage'] = [
            \PDO::PARAM_STR,
            $submittedValues['hg_map_icon_image'] ? $pearDB->escape($submittedValues['hg_map_icon_image']) : null
        ];
    }

    if (isset($submittedValues['hg_rrd_retention'])) {
        $request .= ', hg_rrd_retention = :rrdRetention';
        $bindValues[':rrdRetention'] = [
            \PDO::PARAM_STR,
            $submittedValues['hg_rrd_retention'] ? $pearDB->escape($submittedValues['hg_rrd_retention']): null
        ];
    }

    if (isset($submittedValues['geo_coords'])) {
        $request .= ', geo_coords = :geoCoords';
        $bindValues[':geoCoords'] = [
            \PDO::PARAM_STR,
            $pearDB->escape($submittedValues['geo_coords'])
        ];
    }

    if (isset($submittedValues['hg_comment'])) {
        $request .= ', hg_comment = :comment';
        $bindValues[':comment'] = [
            \PDO::PARAM_STR,
            $pearDB->escape($submittedValues['hg_comment'])
        ];
    }

    if (
        isset($submittedValues['hg_activate']['hg_activate'])
        && $submittedValues['hg_activate']['hg_activate'] !== null
    ) {
        $request .= ', hg_activate = :isActivated';
        $bindValues[':isActivated'] = [
            \PDO::PARAM_STR,
            $submittedValues['hg_activate']['hg_activate']
        ];
    }

    $request .= ' WHERE hg_id = :hostGroupId';

    $bindValues[':hostGroupId'] = [
        \PDO::PARAM_INT,
        $hostGroupId
    ];

    $statement = $pearDB->prepare($request);

    foreach ($bindValues as $bindName => $bindParams) {
        [$bindType, $bindValue] = $bindParams;
        $statement->bindValue($bindName, $bindValue, $bindType);
    }

    $statement->execute();

    $centreon->CentreonLogAction->insertLog(
        object_type: 'hostgroup',
        object_id: $hostGroupId,
        object_name: $pearDB->escape($submittedValues['hg_name']),
        action_type: 'c',
        fields: CentreonLogAction::prepareChanges($submittedValues)
    );
}

function updateHostGroupInDB($hostGroupId = null, bool $isCloudPlatform, array $submittedValues = [], $increment = false)
{
    global $centreon;

    if (! $hostGroupId) {
        return;
    }

    $previousPollerIds = getPollersForConfigChangeFlagFromHostgroupId($hostGroupId);

    updateHostGroup($hostGroupId, $submittedValues, $isCloudPlatform);
    updateHostGroupHosts($hostGroupId, $submittedValues, $increment);

    signalConfigurationChange('hostgroup', $hostGroupId, $previousPollerIds);
    $centreon->user->access->updateACL();
}

function updateHostGroup($hostGroupId = null, array $submittedValues = [], bool $isCloudPlatform)
{
    return $isCloudPlatform
        ? updateHostGroupInDBForCloud($hostGroupId, $submittedValues)
        : updateHostGroupInDBForOnPrem($hostGroupId, $submittedValues);
}

function updateHostGroupHosts($hg_id, $ret = array(), $increment = false)
{
    global $form, $pearDB;

    if (!$hg_id) {
        return;
    }

    /*
	 * Special Case, delete relation between host/service, when service
	 * is linked to hostgroup in escalation, dependencies
	 *
	 * Get initial Host list to make a diff after deletion
	 */
    $hostsOLD = array();
    $statement = $pearDB->prepare("SELECT host_host_id FROM hostgroup_relation
        WHERE hostgroup_hg_id = :hostgroup_hg_id");
    $statement->bindValue(':hostgroup_hg_id', (int) $hg_id, \PDO::PARAM_INT);
    $statement->execute();
    while (($host = $statement->fetch(\PDO::FETCH_ASSOC)) !== false) {
        $hostsOLD[$host["host_host_id"]] = $host["host_host_id"];
    }
    $statement->closeCursor();

    /*
	 * Get service lists linked to hostgroup
	 */
    $rq = "SELECT service_service_id FROM host_service_relation ";
    $rq .= "WHERE hostgroup_hg_id = '" . $hg_id . "' AND host_host_id IS NULL";
    $dbResult = $pearDB->query($rq);
    $hgSVS = array();
    while ($sv = $dbResult->fetch()) {
        $hgSVS[$sv["service_service_id"]] = $sv["service_service_id"];
    }

    /*
	 * Update Host HG relations
	 */
    if ($increment == false) {
        $rq = "DELETE FROM hostgroup_relation ";
        $rq .= "WHERE hostgroup_hg_id = '" . $hg_id . "'";
        $pearDB->query($rq);
    }

    $ret = isset($ret["hg_hosts"]) ? $ret["hg_hosts"] : CentreonUtils::mergeWithInitialValues($form, 'hg_hosts');

    $hgNEW = array();

    $rq = "INSERT INTO hostgroup_relation (hostgroup_hg_id, host_host_id) VALUES ";
    $query = "SELECT hostgroup_hg_id FROM hostgroup_relation WHERE hostgroup_hg_id = :hostgroup_hg_id
        AND host_host_id = :host_host_id";
    $statement = $pearDB->prepare($query);
    $counter = count($ret);
    for ($i = 0; $i < $counter; $i++) {
        $statement->bindValue(':hostgroup_hg_id', (int) $hg_id, \PDO::PARAM_INT);
        $statement->bindValue(':host_host_id', (int) $ret[$i], \PDO::PARAM_INT);
        $statement->execute();
        if (!$statement->rowCount()) {
            if ($i != 0) {
                $rq .= ", ";
            }
            $rq .= " ('" . $hg_id . "', '" . $ret[$i] . "')";
            $hostsNEW[$ret[$i]] = $ret[$i];
        }
    }

    if ($i != 0) {
        $dbResult = $pearDB->query($rq);
    }

    /*
	 * Update HG HG relations
	 */
    if ($increment == false) {
        $statement = $pearDB->prepare("DELETE FROM hostgroup_hg_relation WHERE hg_parent_id = :hg_parent_id");
        $statement->bindValue(':hg_parent_id', (int) $hg_id, \PDO::PARAM_INT);
        $statement->execute();
    }
    isset($ret["hg_hg"]) ? $ret = $ret["hg_hg"] : $ret = $form->getSubmitValue("hg_hg");
    $hgNEW = array();

    $rq = "INSERT INTO hostgroup_hg_relation (hg_parent_id, hg_child_id) VALUES ";
    $loopCount = (is_array($ret) || $ret instanceof Countable) ? count($ret) : 0;

    $query = "SELECT hg_parent_id FROM hostgroup_hg_relation WHERE hg_parent_id = :hg_parent_id
            AND hg_child_id = :hg_child_id";
    $statement = $pearDB->prepare($query);
    for ($i = 0; $i < $loopCount; $i++) {
        $statement->bindValue(':hg_parent_id', (int) $hg_id, \PDO::PARAM_INT);
        $statement->bindValue(':hg_child_id', (int) $ret[$i], \PDO::PARAM_INT);
        $statement->execute();
        if (!$statement->rowCount()) {
            if ($i != 0) {
                $rq .= ", ";
            }
        }
    }
    if ($i != 0) {
        $pearDB->query($rq);
    }

    /*
     * Remove relations that no longer exist (for services by hostgroup)
     */
    $svcObj = new CentreonService($pearDB);
    $svcObj->cleanServiceRelations("escalation_service_relation", "host_host_id", "service_service_id");
    $svcObj->cleanServiceRelations("dependency_serviceChild_relation", "host_host_id", "service_service_id");
    $svcObj->cleanServiceRelations("dependency_serviceParent_relation", "host_host_id", "service_service_id");
    $svcObj->cleanServiceRelations("downtime_service_relation", "host_host_id", "service_service_id");
}

/**
 * @param int $hostgroupId
 * @return int[]
 */
function getPollersForConfigChangeFlagFromHostgroupId(int $hostgroupId): array
{
    $hostIds = findHostsForConfigChangeFlagFromHostGroupIds([$hostgroupId]);
    return findPollersForConfigChangeFlagFromHostIds($hostIds);
}
