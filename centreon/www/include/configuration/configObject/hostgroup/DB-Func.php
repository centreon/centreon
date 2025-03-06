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

use App\Kernel;
use Core\ActionLog\Domain\Model\ActionLog;
use Core\Infrastructure\Common\Api\Router;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

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
        $id = (int) $form->getSubmitValue('hg_id');
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

function enableHostGroupInDB($hg_id = null, $hg_arr = [])
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
        $centreon->CentreonLogAction->insertLog(
            object_type: ActionLog::OBJECT_TYPE_HOSTGROUP,
            object_id: $hostgroupId,
            object_name: $hostgroupName,
            action_type: ActionLog::ACTION_TYPE_ENABLE
        );
    }
}

function disableHostGroupInDB($hg_id = null, $hg_arr = [])
{
    global $pearDB, $centreon;

    if (!$hg_id && !count($hg_arr)) {
        return;
    }
    if ($hg_id) {
        $hg_arr = [$hg_id => "1"];
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
        $centreon->CentreonLogAction->insertLog(
            object_type: ActionLog::OBJECT_TYPE_HOSTGROUP,
            object_id: $hostgroupId,
            object_name: $hostgroupName,
            action_type: ActionLog::ACTION_TYPE_DISABLE
        );
    }
}

/**
 * @param int $hgId
 *
 * @throws CentreonDbException
 */
function removeRelationLastHostgroupDependency(int $hgId): void
{
    global $pearDB;

    try {
        $query = <<<'SQL'
                SELECT COUNT(dependency_dep_id) AS nb_dependency, dependency_dep_id AS id
                FROM dependency_hostgroupParent_relation dhr1
                WHERE dependency_dep_id IN (
                    SELECT dependency_dep_id
                    FROM dependency_hostgroupParent_relation
                    WHERE hostgroup_hg_id = :hgId
                )
                GROUP BY dependency_dep_id
            SQL;

        $statement = $pearDB->prepare($query);
        $statement->bindValue(":hgId", (int) $hgId, \PDO::PARAM_INT);
        $statement->execute();

        //if last parent delete dependency
        while ($result = $statement->fetch()) {
            if (isset($result['nb_dependency']) && $result['nb_dependency'] === 1) {
                $deleteDependencyQuery =
                    <<<'SQL'
                        DELETE FROM dependency
                        WHERE dep_id = :depId
                    SQL;

                $deleteDependencyStatement = $pearDB->prepare($deleteDependencyQuery);
                $deleteDependencyStatement->bindValue(":depId", (int) $result['id'], \PDO::PARAM_INT);
                $deleteDependencyStatement->execute(['depId' => $result['id']]);
            }
        }
    } catch (CentreonDbException $ex) {
        CentreonLog::create()->error(
            CentreonLog::LEVEL_ERROR,
            'Error while removing host group dependencies: ' . $ex->getMessage(),
            ['host_group_id' => $hgId],
            $ex
        );

        throw $ex;
    }
}

/**
 * Deletes a host group and its relations from the database.
 *
 * @param boolean $isCloudPlatform
 * @param array $hostGroups
 *
 * @return void
 */
function deleteHostGroupInApi(bool $isCloudPlatform, array $hostGroups = []): void
{
    global $basePath, $centreon;

    try {
        foreach (array_keys($hostGroups) as $hostGroupId) {
            $previousPollerIds = getPollersForConfigChangeFlagFromHostgroupId((int) $hostGroupId);
            // Delete orphaned dependencies
            removeRelationLastHostgroupDependency((int) $hostGroupId);
            // Delete orphaned services
            deleteOrphanedServices((int) $hostGroupId);
            if ($isCloudPlatform) {
                deleteHostGroupFromDatasetFilters((int) $hostGroupId);
            }

            deleteHostGroupByApi((int) $hostGroupId, $basePath);

            signalConfigurationChange('hostgroup', (int) $hostGroupId, $previousPollerIds);
        }
        $centreon->user->access->updateACL();
    } catch (\Throwable $throwable) {
        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
            message: "Error while deleting hostgroup by API : {$throwable->getMessage()}",
            customContext: ['hostgroups' => $hostGroups, 'basePath' => $basePath],
            exception: $throwable
        );

        return;
    }
}

/**
 * Calls API to delete a hostgroup.
 *
 * @param int $hostGroupId
 * @param string $basePath
 *
 * @return void
 */
function deleteHostGroupByApi(int $hostGroupId, string $basePath): void
{
    $kernel = Kernel::createForWeb();
    $router = $kernel->getContainer()->get(Router::class) ?? throw new LogicException('Router not found in container');
    $url = $router->generate(
        'DeleteHostGroup',
        $basePath ? ['base_uri' => $basePath, 'hostGroupId' => $hostGroupId] : [],
        UrlGeneratorInterface::ABSOLUTE_URL,
    );

    $response = callApi($url, 'DELETE', []);

    if ($response['status_code'] !== 204) {
        $message = $response['content']['message'] ?? 'Unknown error';

        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
            message: "Error while deleting hostgroup by API : {$message}",
            customContext: ['hostGroupId' => $hostGroupId],
        );
    }
}

/**
 * Checks if a service is used by other hostgroups, and if not, deletes it.
 *
 * @param int $hostgroupId
 *
 */
function deleteOrphanedServices(int $hostgroupId): void
{
    global $pearDB;

    $query =  <<<'SQL'
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

    $findStatement= $pearDB->prepare($query);
    $findStatement->bindValue(":hostgroup_id", (int) $hostgroupId, \PDO::PARAM_INT);
    $findStatement->execute();

    $deleteStatement = $pearDB->prepare("DELETE FROM service WHERE service_id = :service_id");

    while ($row = $findStatement->fetch()) {
        if ($row["nbr"] == 1) {
            $deleteStatement->bindValue(':service_id', (int) $row["service_service_id"], \PDO::PARAM_INT);
            $deleteStatement->execute();
        }
    }
}

/**
 * Deletes hostgroup references from dataset filters and cleans up empty filters.
 *
 * @param int $hostGroupId
 *
 * @return void
 */
function deleteHostGroupFromDatasetFilters(int $hostGroupId): void
{
    global $pearDB;

    if ($pearDB->beginTransaction()) {
        try {
            $statement = $pearDB->prepare(
                <<<'SQL'
                    SELECT * FROM dataset_filters
                    INNER JOIN acl_resources_hg_relations arhr
                        ON arhr.acl_res_id = dataset_filters.acl_resource_id
                    WHERE hg_hg_id = :hostgroup_id
                    SQL
            );
            $statement->bindValue(':hostgroup_id', (int) $hostGroupId, \PDO::PARAM_INT);
            $statement->execute();

            while ($datasetFilter = $statement->fetch(\PDO::FETCH_ASSOC)) {
                $resourceIds = explode(',', $datasetFilter['resource_ids']);
                $updatedResourcesIds = array_filter(
                    $resourceIds,
                    fn ($id) => trim($id) !== (string) $hostGroupId
                );
                $resourcesIdsAsString = implode(',', $updatedResourcesIds);
                if (! empty($resourcesIdsAsString)) {
                    $statement = $pearDB->prepare(
                        <<<'SQL'
                            UPDATE dataset_filters
                            SET resource_ids = :resource_ids
                            WHERE id = :id
                            SQL
                    );
                    $statement->bindValue(':resource_ids', $resourcesIdsAsString, \PDO::PARAM_STR);
                    $statement->bindValue(':id', (int) $datasetFilter['id'], \PDO::PARAM_INT);

                    $statement->execute();
                } else {
                    $statement = $pearDB->prepare(
                        <<<'SQL'
                            DELETE FROM dataset_filters
                            WHERE id = :id
                            SQL
                    );
                    $statement->bindValue(':id', (int) $datasetFilter['id'], \PDO::PARAM_INT);

                    $statement->execute();
                }
            }
            $pearDB->commit();
        } catch (\Throwable $exception) {
            $pearDB->rollBack();
            throw $exception;
        }
    }
}

function multipleHostGroupInDB($hostGroups = [], $nbrDup = [])
{
    global $pearDB, $centreon, $is_admin;

    $hgAcl = [];
    foreach ($hostGroups as $key => $value) {
        $dbResult = $pearDB->query("SELECT * FROM hostgroup WHERE hg_id = '" . $key . "' LIMIT 1");
        $row = $dbResult->fetch();
        $row["hg_id"] = null;
        for ($i = 1; $i <= $nbrDup[$key]; $i++) {
            $val = null;
            $rq = null;
            foreach ($row as $key2 => $value2) {
                $value2 = is_int($value2) ? (string) $value2 : $value2;
                if ($key2 == "hg_name") {
                    $hg_name = $value2 . "_" . $i;
                    $value2 = $value2 . "_" . $i;
                }
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
                $rq = $val ? "INSERT INTO hostgroup VALUES (" . $val . ")" : null;
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
                    $centreon->CentreonLogAction->insertLog(
                        object_type: ActionLog::OBJECT_TYPE_HOSTGROUP,
                        object_id: $maxId["MAX(hg_id)"],
                        object_name: $hg_name,
                        action_type: ActionLog::ACTION_TYPE_ADD,
                        fields: $fields
                    );
                }
            }
        }
    }
    CentreonACL::duplicateHgAcl($hgAcl);
    $centreon->user->access->updateACL();
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
                } elseif (! empty($datasetFilterToPopulate['dataset_filter_resources'])) {
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
    } elseif (! $centreon->user->admin) {
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
                    $pearDB->commit();
                } catch (\Throwable $exception) {
                    $pearDB->rollBack();
                    throw $exception;
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

    return (int) $pearDB->lastInsertId();
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

function updateHostGroupHosts($hg_id, $ret = [], $increment = false)
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
    $hostsOLD = [];
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
    $hgSVS = [];
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

    $ret = $ret["hg_hosts"] ?? CentreonUtils::mergeWithInitialValues($form, 'hg_hosts');

    $hgNEW = [];

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
    $ret = $ret["hg_hg"] ?? $form->getSubmitValue("hg_hg");
    $hgNEW = [];

    $rq = "INSERT INTO hostgroup_hg_relation (hg_parent_id, hg_child_id) VALUES ";
    $loopCount = (is_countable($ret)) ? count($ret) : 0;

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

// ---------- API CALLs ----------

/**
 * Create a new host group from formData.
 * @param array formData
 *
 * @return int|null
 */
function insertHostGroup(array $formData): int|false
{
    global $centreon, $isCloudPlatform, $basePath;

    try {
        if (null === ($hostGroupId = insertHostGroupByApi($formData, $isCloudPlatform, $basePath))) {
            throw new Exception('New hostgroup ID invalid');
        }

        updateHostGroupHosts($hostGroupId, $formData);
        updateHostgroupAcl($hostGroupId, $isCloudPlatform, $formData);
        signalConfigurationChange('hostgroup', $hostGroupId);
        $centreon->user->access->updateACL();

        return $hostGroupId;
    } catch (JsonException $ex) {
        CentreonLog::create()->error(CentreonLog::TYPE_BUSINESS_LOG, 'Error during host group creation',
        [
            'hostGroupId' => $hostGroupId ?? null,
            'exception' => ['message' => $ex->getMessage(), 'trace' => $ex->getTraceAsString()],
        ]);
        echo "<div class='msg' align='center'>" . _('Error during creation. See logs for more detail or contact your administrator') . '</div>';

        return false;
    } catch (Throwable $th) {
        CentreonLog::create()->error(CentreonLog::TYPE_BUSINESS_LOG, 'Error during host group creation',
        [
            'hostGroupId' => $hostGroupId ?? null,
            'exception' => ['message' => $th->getMessage(), 'trace' => $th->getTraceAsString()],
        ]);
        echo "<div class='msg' align='center'>" . _($th->getMessage()) . '</div>';

        return false;
    }
}

/**
 * @param int $hgId
 * @param array $formData
 *
 * @return bool
 */
function updateHostGroup(int $hgId, array $formData): bool
{
    global $centreon, $isCloudPlatform, $basePath;

    try {
        $previousPollerIds = getPollersForConfigChangeFlagFromHostgroupId($hgId);

        updateHostGroupByApi($formData, $isCloudPlatform, $basePath);
        updateHostGroupHosts($hgId, $formData);
        signalConfigurationChange('hostgroup', $hgId, $previousPollerIds);
        $centreon->user->access->updateACL();

        return true;
    } catch (JsonException $ex) {
        CentreonLog::create()->error(CentreonLog::TYPE_BUSINESS_LOG, 'Error during host group creation',
        [
            'hostGroupId' => $hostGroupId ?? null,
            'exception' => ['message' => $ex->getMessage(), 'trace' => $ex->getTraceAsString()],
        ]);
        echo "<div class='msg' align='center'>" . _('Error during creation (json encoding). See logs for more detail') . '</div>';

        return false;
    } catch (Throwable $th) {
        CentreonLog::create()->error(CentreonLog::TYPE_BUSINESS_LOG, 'Error during host group creation',
        [
            'hostGroupId' => $hostGroupId ?? null,
            'exception' => ['message' => $th->getMessage(), 'trace' => $th->getTraceAsString()],
        ]);
        echo "<div class='msg' align='center'>" . _($th->getMessage()) . '</div>';

        return false;
    }
}

/**
 * @param array $formData
 * @param bool $isCloudPlatform
 * @param string $basePath
 *
 * @throws LogicException
 * @throws Exception
 *
 * @return int|null
 */
function insertHostGroupByApi(array $formData, bool $isCloudPlatform, string $basePath): int|null
{
    $kernel = Kernel::createForWeb();
    /** @var Router $router */
    $router = $kernel->getContainer()->get(Router::class);

    $payload = getPayload($isCloudPlatform, $formData);

    $url = $router->generate(
        'AddHostGroup',
        $basePath ? ['base_uri' => $basePath] : [],
        UrlGeneratorInterface::ABSOLUTE_URL,
    );

    $response = callApi($url, 'POST', $payload);

    return $response['content']['id'] ?? null;
}

/**
 * @param array $formData
 * @param bool $isCloudPlatform
 * @param string $basePath
 *
 * @throws LogicException
 * @throws Exception
 *
 * @return void
 */
function updateHostGroupByApi(array $formData, bool $isCloudPlatform, string $basePath): void
{
    $kernel = Kernel::createForWeb();
    /** @var Router $router */
    $router = $kernel->getContainer()->get(Router::class)
        ?? throw new LogicException('Router not found in container');

    $payload = getPayload($isCloudPlatform, $formData);

    $parameters = $basePath
        ? ['base_uri' => $basePath, 'hostGroupId' => (int) $formData['hg_id']]
        : [];

    $url = $router->generate(
        'UpdateHostGroup',
        $parameters,
        UrlGeneratorInterface::ABSOLUTE_URL,
    );

    callApi($url, 'PUT', $payload);
}

/**
 * Return ID when httpMethod = POST, null otherwize.
 *
 * @param string $url
 * @param string $httpMethod
 * @param array<string,mixed> $payload
 *
 * @throws Exception
 *
 * @return array<string,mixed>
 */
function callApi(string $url, string $httpMethod, array $payload): array
{
    $client = new CurlHttpClient();
    $response = $client->request(
        $httpMethod,
        $url,
        [
            'headers' => [
                'Content-Type' => 'application/json',
                'Cookie' => 'PHPSESSID=' . $_COOKIE['PHPSESSID'],
            ],
            'body' => json_encode($payload, JSON_THROW_ON_ERROR),
        ],
    );

    $status = $response->getStatusCode();
    if (
        ($httpMethod === 'POST' && $status !== 201)
        || ($httpMethod === 'PUT' && $status !== 204)
    ) {
        $content = json_decode(json: $response->getContent(false), flags: JSON_THROW_ON_ERROR);

        throw new Exception($content->message ?? 'Unexpected return status');
    }

    return ['status_code' => $status, 'content' => json_decode($response->getContent(false), true)];
}

/**
 * @param bool $isCloudPlatform
 * @param array $formData
 *
 * @return array<string,mixed>
 */
function getPayload(bool $isCloudPlatform, array $formData): array
{
    $payload = [
        'name' => $formData['hg_name'],
        'alias' => $formData['hg_alias'] ?: null,
        'geo_coords' => $formData['geo_coords'] ?: null,
        'is_activated' => (bool) ($formData['hg_activate']['hg_activate'] ?: false),
        'hosts' => $formData['hg_hosts'] ? array_map('intval', $formData['hg_hosts'])  : [],
        'comment' => $formData['hg_comment'] ?: null,
    ];

    if ($isCloudPlatform) {
        $payload['resource_access_rules'] = $formData['resource_access_rules']
            ? array_map('intval', $formData['resource_access_rules'])
            : [];
    } else {
        $payload['icon_id'] = $formData['hg_icon_image'] !== '' ? (int) $formData['hg_icon_image'] : null;
    }

    return $payload;
}
