<?php
/*
* Copyright 2005-2015 Centreon
* Centreon is developped by : Julien Mathis and Romain Le Merlus under
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

function testServiceGroupExistence($name = null)
{
    global $pearDB, $form, $centreon;

    $id = null;

    if (isset($form)) {
        $id = $form->getSubmitValue('sg_id');
    }
    $sgName = \HtmlAnalyzer::sanitizeAndRemoveTags($name);

    $statement = $pearDB->prepare("SELECT sg_name, sg_id FROM servicegroup WHERE sg_name = :sg_name");
    $statement->bindValue(':sg_name', $sgName, \PDO::PARAM_STR);
    $statement->execute();
    $sg = $statement->fetch();
    if ($statement->rowCount() >= 1 && $sg["sg_id"] !== (int) $id) {
        return false;
    } else {
        return true;
    }
}

function enableServiceGroupInDB($sgId = null)
{
    if (!$sgId) {
        return;
    }

    global $pearDB, $centreon;

    $sgId = filter_var($sgId, FILTER_VALIDATE_INT);

    $statement = $pearDB->prepare("UPDATE servicegroup SET sg_activate = '1' WHERE sg_id = :sg_id");
    $statement->bindValue(':sg_id', $sgId, \PDO::PARAM_INT);
    $statement->execute();

    $statement2 = $pearDB->prepare("SELECT sg_name FROM `servicegroup` WHERE `sg_id` = :sg_id LIMIT 1");
    $statement2->bindValue(':sg_id', $sgId, \PDO::PARAM_INT);
    $statement2->execute();
    $row = $statement2->fetch();

    signalConfigurationChange('servicegroup', $sgId);
    $centreon->CentreonLogAction->insertLog("servicegroup", $sgId, $row['sg_name'], "enable");
}

function disableServiceGroupInDB($sgId = null)
{
    if (!$sgId) {
        return;
    }
    global $pearDB, $centreon;

    $sgId = filter_var($sgId, FILTER_VALIDATE_INT);

    $statement = $pearDB->prepare("UPDATE servicegroup SET sg_activate = '0' WHERE sg_id = :sg_id");
    $statement->bindValue(':sg_id', $sgId, \PDO::PARAM_INT);
    $statement->execute();

    $statement2 = $pearDB->prepare("SELECT sg_name FROM `servicegroup` WHERE `sg_id` = :sg_id LIMIT 1");
    $statement2->bindValue(':sg_id', $sgId, \PDO::PARAM_INT);
    $statement2->execute();
    $row = $statement2->fetch();

    signalConfigurationChange('servicegroup', $sgId, [], false);
    $centreon->CentreonLogAction->insertLog("servicegroup", $sgId, $row['sg_name'], "disable");
}

/**
 * @param int $servicegroupId
 */
function removeRelationLastServicegroupDependency(int $servicegroupId): void
{
    global $pearDB;

    $query = 'SELECT count(dependency_dep_id) AS nb_dependency , dependency_dep_id AS id
              FROM dependency_servicegroupParent_relation
              WHERE dependency_dep_id = (SELECT dependency_dep_id FROM dependency_servicegroupParent_relation
                                         WHERE servicegroup_sg_id =  ' . $servicegroupId . ')
              GROUP BY dependency_dep_id';
    $dbResult = $pearDB->query($query);
    $result = $dbResult->fetch();

    //is last parent
    if (isset($result['nb_dependency']) && $result['nb_dependency'] == 1) {
        $pearDB->query("DELETE FROM dependency WHERE dep_id = " . $result['id']);
    }
}

/**
 * @param array $serviceGroups
 */
function deleteServiceGroupInDB($serviceGroups = [])
{
    global $pearDB, $centreon;

    foreach (array_keys($serviceGroups) as $key) {
        $sgId = filter_var($key, FILTER_VALIDATE_INT);

        $previousPollerIds = getPollersForConfigChangeFlagFromServicegroupId((int) $sgId);

        removeRelationLastServicegroupDependency((int)$sgId);
        $statement = $pearDB->prepare("SELECT sg_name FROM `servicegroup` WHERE `sg_id` = :sg_id LIMIT 1");
        $statement->bindValue(':sg_id', $sgId, \PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch();

        $statement2 = $pearDB->prepare("DELETE FROM servicegroup WHERE sg_id = :sg_id");
        $statement2->bindValue(':sg_id', $sgId, \PDO::PARAM_INT);
        $statement2->execute();

        signalConfigurationChange('servicegroup', $sgId, $previousPollerIds);
        $centreon->CentreonLogAction->insertLog("servicegroup", $key, $row['sg_name'], "d");
    }
    $centreon->user->access->updateACL();
}

function multipleServiceGroupInDB($serviceGroups = [], $nbrDup = [])
{
    global $pearDB, $centreon;

    $sgAcl = [];
    foreach (array_keys($serviceGroups) as $key) {
        $sgId = filter_var($key, FILTER_VALIDATE_INT);

        $statement = $pearDB->prepare("SELECT * FROM servicegroup WHERE sg_id = :sg_id LIMIT 1");
        $statement->bindValue(':sg_id', $sgId, \PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch();

        $row["sg_id"] = null;

        for ($i = 1; $i <= $nbrDup[$key]; $i++) {
            $bindParams = [];
            foreach ($row as $key2 => $value2) {
                switch ($key2) {
                    case 'sg_name':
                        $value2 = \HtmlAnalyzer::sanitizeAndRemoveTags($value2);
                        $sgName = $value2 . "_" . $i;
                        $value2 = $value2 . "_" . $i;
                        $bindParams[':sg_name'] = [\PDO::PARAM_STR => $value2];
                        break;
                    case 'sg_alias':
                        $value2 = \HtmlAnalyzer::sanitizeAndRemoveTags($value2);
                        $bindParams[':sg_alias'] = [\PDO::PARAM_STR => $value2];
                        break;
                    case 'sg_comment':
                        $value2 = \HtmlAnalyzer::sanitizeAndRemoveTags($value2);
                        $value2
                            ? $bindParams[':sg_comment'] = [\PDO::PARAM_STR => $value2]
                            : $bindParams[':sg_comment'] = [\PDO::PARAM_NULL => null];
                        break;
                    case 'geo_coords':
                        centreonUtils::validateGeoCoords($value2)
                            ? $bindParams[':geo_coords'] = [\PDO::PARAM_STR => $value2]
                            : $bindParams[':geo_coords'] = [\PDO::PARAM_NULL => null];
                        break;
                    case 'sg_activate':
                        $value2 = filter_var($value2, FILTER_VALIDATE_REGEXP, [
                            "options" => [
                                "regexp" => "/^0|1$/"
                            ]
                        ]);
                        $value2
                            ? $bindParams[':sg_activate'] = [\PDO::PARAM_STR => $value2]
                            : $bindParams[':sg_activate'] = [\PDO::PARAM_STR => "0"];
                        break;
                }
                if ($key2 != "sg_id") {
                    $fields[$key2] = $value2;
                }
                if (isset($sgName)) {
                    $fields["sg_name"] = $sgName;
                }
            }
            if (testServiceGroupExistence($sgName)) {
                if (!empty($bindParams)) {
                    $statement = $pearDB->prepare("
                        INSERT INTO servicegroup
                        VALUES (NULL, :sg_name, :sg_alias, :sg_comment, :geo_coords, :sg_activate)
                    ");
                    foreach ($bindParams as $token => $bindValues) {
                        foreach ($bindValues as $paramType => $value) {
                            $statement->bindValue($token, $value, $paramType);
                        }
                    }
                    $statement->execute();
                }
                $dbResult = $pearDB->query("SELECT MAX(sg_id) FROM servicegroup");
                $maxId = $dbResult->fetch();
                if (isset($maxId["MAX(sg_id)"])) {
                    $sgAcl[$maxId["MAX(sg_id)"]] = $sgId;
                    $dbResult->closeCursor();
                    $statement = $pearDB->prepare("
                        SELECT DISTINCT sgr.host_host_id, sgr.hostgroup_hg_id, sgr.service_service_id
                        FROM servicegroup_relation sgr WHERE sgr.servicegroup_sg_id = :sg_id
                    ");
                    $statement->bindValue(':sg_id', $sgId, \PDO::PARAM_INT);
                    $statement->execute();
                    $fields["sg_hgServices"] = "";
                    while ($service = $statement->fetch()) {
                        $bindParams = [];
                        foreach ($service as $key2 => $value2) {
                            switch ($key2) {
                                case 'host_host_id':
                                    $value2 = filter_var($value2, FILTER_VALIDATE_INT);
                                    $value2
                                        ? $bindParams[':host_host_id'] = [\PDO::PARAM_INT => $value2]
                                        : $bindParams[':host_host_id'] = [\PDO::PARAM_NULL => null];
                                    break;
                                case 'hostgroup_hg_id':
                                    $value2 = filter_var($value2, FILTER_VALIDATE_INT);
                                    $value2
                                        ? $bindParams[':hostgroup_hg_id'] = [\PDO::PARAM_INT => $value2]
                                        : $bindParams[':hostgroup_hg_id'] = [\PDO::PARAM_NULL => null];
                                    break;
                                case 'service_service_id':
                                    $value2 = filter_var($value2, FILTER_VALIDATE_INT);
                                    $value2
                                        ? $bindParams[':service_service_id'] = [\PDO::PARAM_INT => $value2]
                                        : $bindParams[':service_service_id'] = [\PDO::PARAM_NULL => null];
                                    break;
                            }
                            $bindParams[':servicegroup_sg_id'] = [\PDO::PARAM_INT => $maxId["MAX(sg_id)"]];
                        }
                        $statement2 = $pearDB->prepare("
                            INSERT INTO servicegroup_relation
                            (host_host_id, hostgroup_hg_id, service_service_id, servicegroup_sg_id)
                            VALUES (:host_host_id, :hostgroup_hg_id, :service_service_id, :servicegroup_sg_id)
                        ");
                        foreach ($bindParams as $token => $bindValues) {
                            foreach ($bindValues as $paramType => $value) {
                                $statement2->bindValue($token, $value, $paramType);
                            }
                        }
                        $statement2->execute();
                        $fields["sg_hgServices"] .= $service["service_service_id"] . ",";
                    }
                    $fields["sg_hgServices"] = trim($fields["sg_hgServices"], ",");

                    signalConfigurationChange('servicegroup', $maxId["MAX(sg_id)"]);
                    $centreon->CentreonLogAction->insertLog(
                        "servicegroup",
                        $maxId["MAX(sg_id)"],
                        $sgName,
                        "a",
                        $fields
                    );
                }
            }
        }
    }
    CentreonACL::duplicateSgAcl($sgAcl);
    $centreon->user->access->updateACL();
}

function updateServiceGroupAcl(int $serviceGroupId, array $submittedValues = []): void
{
    global $pearDB;

    $ruleIds = $submittedValues['resource_access_rules'];

    foreach ($ruleIds as $ruleId) {
        $datasets = findDatasetsByRuleId($ruleId);

        /**
         * see if at least a dataset filter saved is of type servicegroup
         * if so then add the new servicegroup to the dataset
         * otherwise create a new dataset for this servicegroup
         */
        $serviceGroupDatasetFilters = array_values(
            array_filter(
                $datasets,
                fn (array $dataset) => $dataset['dataset_filter_type'] === 'servicegroup'
            )
        );

        // No dataset_filter of type service group found. Create a new one
        if ($serviceGroupDatasetFilters === []) {
            // get the dataset with the highest ID (last one added) which is the first element of the datasets array
            $lastDatasetAdded = $datasets[0];
            preg_match('/dataset_for_rule_\d+_(\d+)/', $lastDatasetAdded['dataset_name'], $matches);
            // calculate the new dataset_name
            $newDatasetName = 'dataset_for_rule_' . $ruleId . '_' . ((int) $matches[1] + 1);
            if ($pearDB->beginTransaction()) {
                try {
                    $datasetId = createNewDataset(datasetName: $newDatasetName);
                    linkDatasetToRule(datasetId: $datasetId, ruleId: $ruleId);
                    linkServiceGroupToDataset(datasetId: $datasetId, serviceGroupId: $serviceGroupId);
                    createNewDatasetFilter(datasetId: $datasetId, ruleId: $ruleId, serviceGroupId: $serviceGroupId);
                    $pearDB->commit();
                } catch (\Throwable $exception) {
                    $pearDB->rollBack();
                    throw $exception;
                }
            }
        } elseif ($pearDB->beginTransaction()) {
            try {
                linkServiceGroupToDataset(datasetId: $serviceGroupDatasetFilters[0]['dataset_id'], serviceGroupId: $serviceGroupId);
                // Expend the existing hostgroup dataset_filter
                $expendedResourceIds = $serviceGroupDatasetFilters[0]['dataset_filter_resources'] . ', ' . $serviceGroupId;

                updateDatasetFiltersResourceIds(
                    datasetFilterId: $serviceGroupDatasetFilters[0]['dataset_filter_id'],
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
 * @param int $datasetId
 * @param int $serviceGroupId
 */
function linkServiceGroupToDataset(int $datasetId, int $serviceGroupId): void
{
    global $pearDB;

    $query = <<<SQL
        INSERT INTO acl_resources_sg_relations (sg_id, acl_res_id) VALUES (:serviceGroupId, :datasetId)
    SQL;

    $statement = $pearDB->prepare($query);
    $statement->bindValue(':datasetId', $datasetId, \PDO::PARAM_INT);
    $statement->bindValue(':serviceGroupId', $serviceGroupId, \PDO::PARAM_INT);
    $statement->execute();
}

/**
 * @param int $datasetId
 * @param int $ruleId
 * @param int $serviceGroupId
 */
function createNewDatasetFilter(int $datasetId, int $ruleId, int $serviceGroupId): void
{
    global $pearDB;

    $query = <<<SQL
        INSERT INTO dataset_filters (`type`, acl_resource_id, acl_group_id, resource_ids)
        VALUES ('servicegroup', :datasetId, :ruleId, :serviceGroupId)
    SQL;

    $statement = $pearDB->prepare($query);
    $statement->bindValue(':datasetId', $datasetId, \PDO::PARAM_INT);
    $statement->bindValue(':ruleId', $ruleId, \PDO::PARAM_INT);
    $statement->bindValue(':serviceGroupId', $serviceGroupId, \PDO::PARAM_STR);

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

function insertServiceGroupInDB(bool $isCloudPlatform = false, array $submittedValues = [])
{
    global $centreon, $form;

    $submittedValues = $submittedValues ?: $form->getSubmitValues();

    $serviceGroupId = insertServiceGroup($submittedValues);
    updateServiceGroupServices($serviceGroupId, $submittedValues);

    // Only apply ACL for cloud context
    if ($isCloudPlatform) {
        updateServiceGroupAcl(serviceGroupId: $serviceGroupId, submittedValues: $submittedValues);
    }

    signalConfigurationChange('servicegroup', $serviceGroupId);
    $centreon->user->access->updateACL();
    return $serviceGroupId;
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

function updateServiceGroupInDB(
    bool $isCloudPlatform = false,
    $serviceGroupId = null,
    $submittedValues = [],
    $increment = false
) {
    global $centreon, $form;

    if (! $serviceGroupId) {
        return;
    }

    $submittedValues = $submittedValues ?: $form->getSubmitValues();

    $previousPollerIds = getPollersForConfigChangeFlagFromServiceGroupId($serviceGroupId);

    updateServiceGroup($serviceGroupId, $submittedValues);
    updateServiceGroupServices($serviceGroupId, $submittedValues, $increment);

    if ($isCloudPlatform) {
        updateServiceGroupAcl(serviceGroupId: $serviceGroupId, submittedValues: $submittedValues);
    }

    signalConfigurationChange('servicegroup', $serviceGroupId, $previousPollerIds);
    $centreon->user->access->updateACL();
}

function insertServiceGroup($submittedValues = [])
{
    global $pearDB, $centreon;

    $bindParams = [];
    foreach ($submittedValues as $key => $value) {
        switch ($key) {
            case 'sg_name':
                $value = \HtmlAnalyzer::sanitizeAndRemoveTags($value);
                $bindParams[':sg_name'] = [\PDO::PARAM_STR => $value];
                break;
            case 'sg_alias':
                $value = \HtmlAnalyzer::sanitizeAndRemoveTags($value);
                $bindParams[':sg_alias'] = [\PDO::PARAM_STR => $value];
                break;
            case 'sg_comment':
                $value = \HtmlAnalyzer::sanitizeAndRemoveTags($value);
                $value
                    ? $bindParams[':sg_comment'] = [\PDO::PARAM_STR => $value]
                    : $bindParams[':sg_comment'] = [\PDO::PARAM_NULL => null];
                break;
            case 'geo_coords':
                centreonUtils::validateGeoCoords($value)
                    ? $bindParams[':geo_coords'] = [\PDO::PARAM_STR => $value]
                    : $bindParams[':geo_coords'] = [\PDO::PARAM_NULL => null];
                break;
            case 'sg_activate':
                $value = filter_var($value['sg_activate'], FILTER_VALIDATE_REGEXP, [
                    "options" => [
                        "regexp" => "/^0|1$/"
                    ]
                ]);
                $value
                    ? $bindParams[':sg_activate'] = [\PDO::PARAM_STR => $value]
                    : $bindParams[':sg_activate'] = [\PDO::PARAM_STR => "0"];
                break;
        }
    }

    $statement = $pearDB->prepare("
        INSERT INTO servicegroup (sg_name, sg_alias, sg_comment, geo_coords, sg_activate)
        VALUES (:sg_name, :sg_alias, :sg_comment, :geo_coords, :sg_activate)
    ");
    foreach ($bindParams as $token => $bindValues) {
        foreach ($bindValues as $paramType => $value) {
            $statement->bindValue($token, $value, $paramType);
        }
    }
    $statement->execute();

    $dbResult = $pearDB->query("SELECT MAX(sg_id) FROM servicegroup");
    $sgId = $dbResult->fetch();
    $dbResult->closeCursor();

    /* Prepare value for changelog */
    $fields = CentreonLogAction::prepareChanges($submittedValues);
    $centreon->CentreonLogAction->insertLog(
        "servicegroup",
        $sgId["MAX(sg_id)"],
        htmlentities($submittedValues["sg_name"], ENT_QUOTES, "UTF-8"),
        "a",
        $fields
    );

    return ($sgId["MAX(sg_id)"]);
}

function updateServiceGroup($serviceGroupId, $submittedValues = [])
{
    global $pearDB, $centreon;

    if (! $serviceGroupId) {
        return;
    }

    $bindParams = [];
    $serviceGroupId = filter_var($serviceGroupId, FILTER_VALIDATE_INT);
    $bindParams[':sg_id'] = [\PDO::PARAM_INT => $serviceGroupId];
    foreach ($submittedValues as $key => $value) {
        switch ($key) {
            case 'sg_name':
                $value = \HtmlAnalyzer::sanitizeAndRemoveTags($value);
                $bindParams[':sg_name'] = [\PDO::PARAM_STR => $value];
                break;
            case 'sg_alias':
                $value = \HtmlAnalyzer::sanitizeAndRemoveTags($value);
                $bindParams[':sg_alias'] = [\PDO::PARAM_STR => $value];
                break;
            case 'sg_comment':
                $value = \HtmlAnalyzer::sanitizeAndRemoveTags($value);
                $value
                    ? $bindParams[':sg_comment'] = [\PDO::PARAM_STR => $value]
                    : $bindParams[':sg_comment'] = [\PDO::PARAM_NULL => null];
                break;
            case 'geo_coords':
                centreonUtils::validateGeoCoords($value)
                    ? $bindParams[':geo_coords'] = [\PDO::PARAM_STR => $value]
                    : $bindParams[':geo_coords'] = [\PDO::PARAM_NULL => null];
                break;
            case 'sg_activate':
                $value = filter_var($value['sg_activate'], FILTER_VALIDATE_REGEXP, [
                    "options" => [
                        "regexp" => "/^0|1$/"
                    ]
                ]);
                $value
                    ? $bindParams[':sg_activate'] = [\PDO::PARAM_STR => $value]
                    : $bindParams[':sg_activate'] = [\PDO::PARAM_STR => "0"];
                break;
        }
    }

    $statement = $pearDB->prepare(<<<'SQL'
        UPDATE servicegroup SET
            sg_name = :sg_name,
            sg_alias = :sg_alias,
            sg_comment = :sg_comment,
            geo_coords = :geo_coords,
            sg_activate = :sg_activate
        WHERE sg_id = :sg_id
        SQL
    );

    foreach ($bindParams as $token => $bindValues) {
        foreach ($bindValues as $paramType => $value) {
            $statement->bindValue($token, $value, $paramType);
        }
    }
    $statement->execute();

    /* Prepare value for changelog */
    $fields = CentreonLogAction::prepareChanges($submittedValues);
    $centreon->CentreonLogAction->insertLog(
        "servicegroup",
        $serviceGroupId,
        htmlentities($submittedValues["sg_name"], ENT_QUOTES, "UTF-8"),
        "c",
        $fields
    );
}

function updateServiceGroupServices($sgId, $ret = [], $increment = false)
{
    if (!$sgId) {
        return;
    }
    global $pearDB, $form;

    $sgId = filter_var($sgId, FILTER_VALIDATE_INT);

    if ($increment == false && $sgId !== false) {
        $statement = $pearDB->prepare("
            DELETE FROM servicegroup_relation
            WHERE servicegroup_sg_id = :sg_id
        ");
        $statement->bindValue(':sg_id', $sgId, \PDO::PARAM_INT);
        $statement->execute();
    }

    /* service templates */
    $retTmp = isset($ret["sg_tServices"]) ? $ret["sg_tServices"] : $form->getSubmitValue("sg_tServices");
    if ($retTmp) {
        $statement = $pearDB->prepare("
            SELECT servicegroup_sg_id service FROM servicegroup_relation
            WHERE host_host_id = :host_host_id AND service_service_id = :service_service_id
            AND servicegroup_sg_id = :sg_id
        ");

        $statement2 = $pearDB->prepare("
            INSERT INTO servicegroup_relation (host_host_id, service_service_id, servicegroup_sg_id)
            VALUES (:host_host_id, :service_service_id, :servicegroup_sg_id)
        ");
        $counter = count($retTmp);
        for ($i = 0; $i < $counter; $i++) {
            if (isset($retTmp[$i]) && $retTmp[$i]) {
                $t = preg_split("/\-/", $retTmp[$i]);
                $hostHostId = filter_var($t[0], FILTER_VALIDATE_INT);
                $serviceServiceId = filter_var($t[1], FILTER_VALIDATE_INT);
                $statement->bindValue(':host_host_id', $hostHostId, \PDO::PARAM_INT);
                $statement->bindValue(':service_service_id', $serviceServiceId, \PDO::PARAM_INT);
                $statement->bindValue(':sg_id', $sgId, \PDO::PARAM_INT);
                $statement->execute();
                if (!$statement->rowCount()) {
                    $statement2->bindValue(':host_host_id', $hostHostId, \PDO::PARAM_INT);
                    $statement2->bindValue(':service_service_id', $serviceServiceId, \PDO::PARAM_INT);
                    $statement2->bindValue(':servicegroup_sg_id', $sgId, \PDO::PARAM_INT);
                    $statement2->execute();
                }
            }
        }
    }

    /* regular services */
    $retTmp = isset($ret["sg_hServices"])
        ? $ret["sg_hServices"]
        : CentreonUtils::mergeWithInitialValues($form, 'sg_hServices');

    $statement = $pearDB->prepare("
        SELECT servicegroup_sg_id service FROM servicegroup_relation
        WHERE host_host_id = :host_host_id AND service_service_id = :service_service_id
        AND servicegroup_sg_id = :sg_id
    ");

    $statement2 = $pearDB->prepare("
        INSERT INTO servicegroup_relation (host_host_id, service_service_id, servicegroup_sg_id)
        VALUES (:host_host_id, :service_service_id, :servicegroup_sg_id)
    ");
    $counter = count($retTmp);
    for ($i = 0; $i < $counter; $i++) {
        if (isset($retTmp[$i]) && $retTmp[$i]) {
            $t = preg_split("/\-/", $retTmp[$i]);
            $hostHostId = filter_var($t[0], FILTER_VALIDATE_INT);
            $serviceServiceId = filter_var($t[1], FILTER_VALIDATE_INT);
            $statement->bindValue(':host_host_id', $hostHostId, \PDO::PARAM_INT);
            $statement->bindValue(':service_service_id', $serviceServiceId, \PDO::PARAM_INT);
            $statement->bindValue(':sg_id', $sgId, \PDO::PARAM_INT);
            $statement->execute();
            if (!$statement->rowCount()) {
                $statement2->bindValue(':host_host_id', $hostHostId, \PDO::PARAM_INT);
                $statement2->bindValue(':service_service_id', $serviceServiceId, \PDO::PARAM_INT);
                $statement2->bindValue(':servicegroup_sg_id', $sgId, \PDO::PARAM_INT);
                $statement2->execute();
            }
        }
    }

    /* hostgroup services */
    $retTmp = isset($ret["sg_hgServices"])
        ? $ret["sg_hgServices"]
        : CentreonUtils::mergeWithInitialValues($form, 'sg_hgServices');

    $statement = $pearDB->prepare("
        SELECT servicegroup_sg_id service FROM servicegroup_relation
        WHERE hostgroup_hg_id = :hostgroup_hg_id AND service_service_id = :service_service_id
        AND servicegroup_sg_id = :servicegroup_sg_id
    ");

    $statement2 = $pearDB->prepare("
        INSERT INTO servicegroup_relation (hostgroup_hg_id, service_service_id, servicegroup_sg_id)
        VALUES (:hostgroup_hg_id, :service_service_id, :servicegroup_sg_id)
    ");
    $counter = count($retTmp);
    for ($i = 0; $i < $counter; $i++) {
        $t = preg_split("/\-/", $retTmp[$i]);
        $hostGroupId = filter_var($t[0], FILTER_VALIDATE_INT);
        $serviceServiceId = filter_var($t[1], FILTER_VALIDATE_INT);
        $statement->bindValue(':hostgroup_hg_id', $hostGroupId, \PDO::PARAM_INT);
        $statement->bindValue(':service_service_id', $serviceServiceId, \PDO::PARAM_INT);
        $statement->bindValue(':servicegroup_sg_id', $sgId, \PDO::PARAM_INT);
        $statement->execute();
        if (!$statement->rowCount()) {
            $statement2->bindValue(':hostgroup_hg_id', $hostGroupId, \PDO::PARAM_INT);
            $statement2->bindValue(':service_service_id', $serviceServiceId, \PDO::PARAM_INT);
            $statement2->bindValue(':servicegroup_sg_id', $sgId, \PDO::PARAM_INT);
            $statement2->execute();
        }
    }
}

/**
 * @param int $servicegroupId
 * @return int[]
 */
function getPollersForConfigChangeFlagFromServiceGroupId(int $servicegroupId): array
{
    $hostIds = findHostsForConfigChangeFlagFromServiceGroupId($servicegroupId);
    return findPollersForConfigChangeFlagFromHostIds($hostIds);
}
