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

use Core\ActionLog\Domain\Model\ActionLog;

if (!isset($oreon)) {
    exit();
}

/**
 * Rule that checks whether severity data is set
 */
function checkSeverity($fields)
{
    $arr = [];
    if (isset($fields['sc_type']) && $fields['sc_severity_level'] == "") {
        $arr['sc_severity_level'] = "Severity level is required";
    }
    if (isset($fields['sc_type']) && $fields['sc_severity_icon'] == "") {
        $arr['sc_severity_icon'] = "Severity icon is required";
    }
    if ($arr !== []) {
        return $arr;
    }
    return true;
}

function testServiceCategorieExistence($name = null)
{
    global $pearDB, $form;

    $name = \HtmlAnalyzer::sanitizeAndRemoveTags($name);
    $id = null;
    if (isset($form)) {
        $id = $form->getSubmitValue('sc_id');
    }
    $query = "SELECT `sc_name`, `sc_id` FROM `service_categories` WHERE `sc_name` = :sc_name";
    $statement = $pearDB->prepare($query);
    $statement->bindValue(':sc_name', $name, \PDO::PARAM_STR);
    $statement->execute();
    $sc = $statement->fetch();
    if ($statement->rowCount() >= 1 && $sc["sc_id"] != $id) {
        return false;
    } else {
        return true;
    }
}

function shouldNotBeEqTo0($value)
{
    if ($value) {
        return true;
    } else {
        return false;
    }
}

function multipleServiceCategorieInDB($sc = [], $nbrDup = [])
{
    global $pearDB, $centreon;

    $scAcl = [];
    foreach ($sc as $key => $value) {
        $scId = filter_var($key, FILTER_VALIDATE_INT);
        $query = "SELECT * FROM `service_categories` WHERE `sc_id` = :sc_id LIMIT 1";
        $statement = $pearDB->prepare($query);
        $statement->bindValue(':sc_id', $scId, \PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch();
        for ($i = 1; $i <= $nbrDup[$scId]; $i++) {
            $val = null;
            $bindParams = [];
            $fields = [];
            foreach ($row as $key2 => $value2) {
                $value2 = is_int($value2) ? (string) $value2 : $value2;
                switch ($key2) {
                    case 'sc_name':
                        $value2 = \HtmlAnalyzer::sanitizeAndRemoveTags($value2);
                        $sc_name = $value2 . "_" . $i;
                        $value2 = $value2 . "_" . $i;
                        $bindParams[':sc_name'] = [
                            \PDO::PARAM_STR => $value2
                        ];
                        break;
                    case 'sc_description':
                        $value2 = \HtmlAnalyzer::sanitizeAndRemoveTags($value2);
                        $bindParams[':sc_description'] = [
                            \PDO::PARAM_STR => $value2
                        ];
                        break;
                    case 'level':
                        $value2 = filter_var($value2, FILTER_VALIDATE_INT);
                        $value2
                            ? $bindParams[':sc_level'] = [\PDO::PARAM_INT => $value2]
                            : $bindParams[':sc_level'] = [\PDO::PARAM_NULL => "NULL"];
                        break;
                    case 'icon_id':
                        $value2 = filter_var($value2, FILTER_VALIDATE_INT);
                        $value2
                            ? $bindParams[':sc_icon_id'] = [\PDO::PARAM_INT => $value2]
                            : $bindParams[':sc_icon_id'] = [\PDO::PARAM_NULL => "NULL"];
                        break;
                    case 'sc_activate':
                        $value2 = filter_var($value2, FILTER_VALIDATE_INT);
                        $value2
                            ? $bindParams[':sc_activate'] = [\PDO::PARAM_STR => $value2]
                            : $bindParams[':sc_activate'] = [\PDO::PARAM_STR =>  "0"];
                        break;
                }
                $val
                    ? $val .= ($value2 != null ? (", '" . $value2 . "'") : ", NULL")
                    : $val .= ($value2 != null ? ("'" . $value2 . "'") : "NULL");

                if ($key2 != "sc_id") {
                    $fields[$key2] = $value2;
                }
            }
            if ($val === null) {
                continue;
            }
            $fields["sc_name"] = $sc_name;
            if (testServiceCategorieExistence($sc_name)) {
                $statement = $pearDB->prepare(
                    <<<SQL
                        INSERT INTO `service_categories`
                        VALUES (NULL, :sc_name, :sc_description, :sc_level, :sc_icon_id, :sc_activate)
                    SQL
                );
                foreach ($bindParams as $token => $bindValues) {
                    foreach ($bindValues as $paramType => $value) {
                        $statement->bindValue($token, $value, $paramType);
                    }
                }
                $statement->execute();
                $statement = $pearDB->query("SELECT MAX(sc_id) as maxid FROM `service_categories`");
                $maxId = $statement->fetch();

                if (isset($maxId['maxid'])) {
                    $scAcl[$maxId['maxid']] = $scId;
                    try {
                        $selectServiceIdsStatement = $pearDB->prepareQuery(
                            <<<SQL
                                SELECT service_service_id FROM service_categories_relation
                                WHERE sc_id = :sc_id
                            SQL
                        );
                        $pearDB->executePreparedQuery($selectServiceIdsStatement, ['sc_id' => $scId]);
                        $insertNewRelationStatement = $pearDB->prepareQuery(
                            <<<SQL
                                INSERT INTO service_categories_relation (service_service_id, sc_id)
                                VALUES (:serviceId, :maxId)
                            SQL
                        );
                        $foundServiceIds = [];
                        while ($serviceId = $selectServiceIdsStatement->fetchColumn()) {
                            $pearDB->executePreparedQuery($insertNewRelationStatement, [
                                'serviceId' => $serviceId,
                                'maxId' => $maxId['maxid']
                            ]);
                            $foundServiceIds[] = $serviceId;
                        }
                        if (! empty($foundServiceIds)) {
                            $fields["sc_services"] = implode(", ", $foundServiceIds);
                        }

                        $centreon->CentreonLogAction->insertLog(
                            object_type: ActionLog::OBJECT_TYPE_SERVICECATEGORIES,
                            object_id: $maxId['maxid'],
                            object_name: $sc_name,
                            action_type: ActionLog::ACTION_TYPE_ADD,
                            fields: $fields
                        );
                    } catch (CentreonDbException $ex) {
                        CentreonLog::create()->error(
                            logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
                            message: $ex->getMessage(),
                            customContext: ['service_category_id' => $scId],
                            exception: $ex,
                        );

                        throw $ex;
                }
            }
        }
    }
    CentreonACL::duplicateScAcl($scAcl);
    $centreon->user->access->updateACL();
}

function enableServiceCategorieInDB(?int $serviceCategoryId = null, array $serviceCategories = [])
{

    if (! $serviceCategoryId && empty($serviceCategories)) {
        return;
    }

    global $pearDB, $centreon;
    if ($serviceCategoryId) {
        $serviceCategories = [$serviceCategoryId => "1"];
    }

    try {
        $updateStatement = $pearDB->prepareQuery(
            <<<SQL
                UPDATE service_categories
                SET sc_activate = '1'
                WHERE sc_id = :serviceCategoryId
            SQL
        );
        $selectStatement = $pearDB->prepareQuery(
            <<<SQL
                SELECT sc_name FROM `service_categories`
                WHERE `sc_id` = :serviceCategoryId LIMIT 1
            SQL
        );
        foreach (array_keys($serviceCategories) as $serviceCategoryId) {
            $pearDB->executePreparedQuery($updateStatement, ['serviceCategoryId' => $serviceCategoryId]);
            $pearDB->executePreparedQuery($selectStatement, ['serviceCategoryId' => $serviceCategoryId]);

            $result = $selectStatement->fetch();
            $centreon->CentreonLogAction->insertLog(
                object_type: ActionLog::OBJECT_TYPE_SERVICECATEGORIES,
                object_id: $serviceCategoryId,
                object_name: $result['sc_name'],
                action_type: ActionLog::ACTION_TYPE_ENABLE
            );
        }
    } catch(CentreonDbException $ex) {
        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
            message: $ex->getMessage(),
            customContext: ['service_category_id' => $serviceCategoryId],
            exception: $ex,
        );

        throw $ex;
    }
}

function disableServiceCategorieInDB(?int $serviceCategoryId = null, array $serviceCategories = [])
{
    if (! $serviceCategoryId && empty($serviceCategories)) {
        return;
    }

    global $pearDB, $centreon;
    if ($serviceCategoryId) {
        $serviceCategories = [$serviceCategoryId => "1"];
    }

    try {
        $updateStatement = $pearDB->prepareQuery(
            <<<SQL
                UPDATE service_categories
                SET sc_activate = '0'
                WHERE sc_id = :serviceCategoryId
            SQL
        );
        $selectStatement = $pearDB->prepareQuery(
            <<<SQL
                SELECT sc_name FROM `service_categories`
                WHERE `sc_id` = :serviceCategoryId LIMIT 1
            SQL
        );
        foreach (array_keys($serviceCategories) as $serviceCategoryId) {
            $pearDB->executePreparedQuery($updateStatement, ['serviceCategoryId' => $serviceCategoryId]);
            $pearDB->executePreparedQuery($selectStatement, ['serviceCategoryId' => $serviceCategoryId]);

            $result = $selectStatement->fetch();
            $centreon->CentreonLogAction->insertLog(
                object_type: ActionLog::OBJECT_TYPE_SERVICECATEGORIES,
                object_id: $serviceCategoryId,
                object_name: $result['sc_name'],
                action_type: ActionLog::ACTION_TYPE_DISABLE
            );
        }
    } catch (CentreonDbException $ex) {
        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
            message: $ex->getMessage(),
            customContext: ['service_category_id' => $implode(', ', $serviceCategories)],
            exception: $ex,
        );

        throw $ex;
    }
}

function insertServiceCategorieInDB()
{
    global $form, $pearDB, $centreon;

    $formValues = $form->getSubmitValues();
    $scName = HtmlSanitizer::createFromString($formValues['sc_name'])->sanitize()->getString();
    $scDescription = HtmlSanitizer::createFromString($formValues['sc_description'])->sanitize()->getString();
    $scSeverityLevel = filter_var($formValues['sc_severity_level'], FILTER_VALIDATE_INT);
    $scType = filter_var($formValues['sc_type'] ?? false, FILTER_VALIDATE_INT);
    $scSeverityIconId = filter_var($formValues['sc_severity_icon'], FILTER_VALIDATE_INT);
    $scActivate = filter_var($formValues['sc_activate']['sc_activate'], FILTER_VALIDATE_INT);

    $bindParams = [];
    $bindParams[':sc_name'] = [
        \PDO::PARAM_STR => $scName
    ];
    $bindParams[':sc_description'] = [
        \PDO::PARAM_STR => $scDescription
    ];
    ($scSeverityLevel === false || $scType === false)
        ? $bindParams[':sc_severity_level'] = [\PDO::PARAM_NULL => "NULL"]
        : $bindParams[':sc_severity_level'] = [\PDO::PARAM_INT => $scSeverityLevel];

    ($scSeverityIconId === false || $scType === false)
        ? $bindParams[':sc_icon_id'] = [\PDO::PARAM_NULL => "NULL"]
        : $bindParams[':sc_icon_id'] = [\PDO::PARAM_INT => $scSeverityIconId];

    ($scActivate === false)
        ? $bindParams[':sc_activate'] = [\PDO::PARAM_STR => "0"]
        : $bindParams[':sc_activate'] = [\PDO::PARAM_STR => $scActivate];
    if (testServiceCategorieExistence($scName)) {
        $query = "
            INSERT INTO `service_categories` (`sc_name`, `sc_description`, `level`, `icon_id`, `sc_activate`)
            VALUES (:sc_name, :sc_description, :sc_severity_level, :sc_icon_id, :sc_activate)";
        $statement = $pearDB->prepare($query);

        foreach ($bindParams as $token => $bindValues) {
            foreach ($bindValues as $paramType => $value) {
                $statement->bindValue($token, $value, $paramType);
            }
        }
        $statement->execute();

        $query = "SELECT MAX(sc_id) FROM `service_categories` WHERE sc_name LIKE :sc_name";
        $statement = $pearDB->prepare($query);
        $statement->bindValue(':sc_name', $scName, \PDO::PARAM_STR);
        $statement->execute();
        $data = $statement->fetch();
    }
    updateServiceCategoriesServices($data["MAX(sc_id)"]);
    $centreon->user->access->updateACL();
    $fields = CentreonLogAction::prepareChanges($formValues);
    $centreon->CentreonLogAction->insertLog(
        object_type: ActionLog::OBJECT_TYPE_SERVICECATEGORIES,
        object_id: $data["MAX(sc_id)"],
        object_name: $scName,
        action_type: ActionLog::ACTION_TYPE_ADD,
        fields: $fields
    );
}

function updateServiceCategorieInDB()
{
    global $form, $pearDB, $centreon;
    $formValues = $form->getSubmitValues();
    $scId = filter_var($formValues['sc_id'], FILTER_VALIDATE_INT);
    $scName = HtmlSanitizer::createFromString($formValues['sc_name'])->sanitize()->getString();
    $scDescription = HtmlSanitizer::createFromString($formValues['sc_description'])->sanitize()->getString();
    $scSeverityLevel = filter_var($formValues['sc_severity_level'], FILTER_VALIDATE_INT);
    $scType = filter_var($formValues['sc_type'] ?? false, FILTER_VALIDATE_INT);
    $scSeverityIconId = filter_var($formValues['sc_severity_icon'], FILTER_VALIDATE_INT);
    $scActivate = filter_var($formValues['sc_activate']['sc_activate'], FILTER_VALIDATE_INT);

    $bindParams = [];
    $bindParams[':sc_id'] = [
        \PDO::PARAM_INT => $scId
    ];
    $bindParams[':sc_name'] = [
        \PDO::PARAM_STR => $scName
    ];
    $bindParams[':sc_description'] = [
        \PDO::PARAM_STR => $scDescription
    ];
    ($scSeverityLevel === false || $scType === false)
        ? $bindParams[':sc_severity_level'] = [\PDO::PARAM_NULL => "NULL"]
        : $bindParams[':sc_severity_level'] = [\PDO::PARAM_INT => $scSeverityLevel];

    ($scSeverityIconId === false || $scType === false)
        ? $bindParams[':sc_icon_id'] = [\PDO::PARAM_NULL => "NULL"]
        : $bindParams[':sc_icon_id'] = [\PDO::PARAM_INT => $scSeverityIconId];

    ($scActivate === false)
        ? $bindParams[':sc_activate'] = [\PDO::PARAM_STR => '0']
        : $bindParams[':sc_activate'] = [\PDO::PARAM_STR => $scActivate];

    $query = "
        UPDATE `service_categories`
        SET `sc_name` = :sc_name,
            `sc_description` = :sc_description,
            `level` = :sc_severity_level,
            `icon_id` = :sc_icon_id,
            `sc_activate` = :sc_activate
        WHERE `sc_id` = :sc_id";
    $statement = $pearDB->prepare($query);
    foreach ($bindParams as $token => $bindValues) {
        foreach ($bindValues as $paramType => $value) {
            $statement->bindValue($token, $value, $paramType);
        }
    }
    $statement->execute();

    updateServiceCategoriesServices($scId);
    $centreon->user->access->updateACL();
    $fields = CentreonLogAction::prepareChanges($formValues);
    $centreon->CentreonLogAction->insertLog(
        object_type: ActionLog::OBJECT_TYPE_SERVICECATEGORIES,
        object_id: $scId,
        object_name: $scName,
        action_type: ActionLog::ACTION_TYPE_CHANGE,
        fields: $fields
    );
}

function deleteServiceCategorieInDB($serviceCategoryIds = null)
{
    global $pearDB, $centreon;

    if (is_null($serviceCategoryIds)) {
        return;
    }

    try {
        $deleteStatement = $pearDB->prepareQuery(
            <<<SQL
                DELETE FROM `service_categories`
                WHERE `sc_id` = :sc_id
            SQL
        );
        $selectStatement = $pearDB->prepareQuery(
            <<<SQL
                SELECT sc_name FROM `service_categories`
                WHERE `sc_id` = :serviceCategoryId LIMIT 1
            SQL
        );
        foreach (array_keys($serviceCategoryIds) as $serviceCategoryId) {
            $serviceCategoryId = filter_var($serviceCategoryId, FILTER_VALIDATE_INT)
                ?: throw new \Exception("Invalid service category id");

            $pearDB->executePreparedQuery($selectStatement, ['serviceCategoryId' => $serviceCategoryId]);
            $result = $selectStatement->
            $pearDB->executePreparedQuery($deleteStatement, ['sc_id' => $serviceCategoryId]);
            $centreon->CentreonLogAction->insertLog(
                object_type: ActionLog::OBJECT_TYPE_SERVICECATEGORIES,
                object_id: $serviceCategoryId,
                object_name: $result['sc_name'],
                action_type: ActionLog::ACTION_TYPE_DELETE
            );
        }
        $centreon->user->access->updateACL();
    } catch(CentreonDbException $ex) {
        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
            message: $ex->getMessage(),
            customContext: ['service_category_id' => implode(', ', $serviceCategoryIds)],
            exception: $ex,
        );

        throw $ex;
    }
}

function updateServiceCategoriesServices(int $sc_id)
{
    global $pearDB, $form;

    if (!$sc_id) {
        return;
    }
    $query = "
        DELETE FROM service_categories_relation WHERE sc_id = :sc_id
        AND service_service_id IN (SELECT service_id FROM service WHERE service_register = '0')";
    $statement = $pearDB->prepare($query);
    $statement->bindValue(':sc_id', $sc_id, \PDO::PARAM_INT);
    $statement->execute();
    if (isset($_POST["sc_svcTpl"])) {
        foreach ($_POST["sc_svcTpl"] as $serviceId) {
            $serviceId = filter_var($serviceId, FILTER_VALIDATE_INT);
            $query = "
                INSERT INTO service_categories_relation (service_service_id, sc_id)
                VALUES (:service_id, :sc_id)";
            $statement = $pearDB->prepare($query);
            $statement->bindValue(':service_id', $serviceId, \PDO::PARAM_INT);
            $statement->bindValue(':sc_id', $sc_id, \PDO::PARAM_INT);
            $statement->execute();
        }
    }
}
