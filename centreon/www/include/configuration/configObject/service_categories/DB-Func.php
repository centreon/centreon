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

use Core\ActionLog\Domain\Model\ActionLog;

if (! isset($oreon)) {
    exit();
}

/**
 * Rule that checks whether severity data is set
 * @param mixed $fields
 */
function checkSeverity($fields)
{
    $arr = [];
    if (isset($fields['sc_type']) && $fields['sc_severity_level'] == '') {
        $arr['sc_severity_level'] = 'Severity level is required';
    }
    if (isset($fields['sc_type']) && $fields['sc_severity_icon'] == '') {
        $arr['sc_severity_icon'] = 'Severity icon is required';
    }
    if ($arr !== []) {
        return $arr;
    }

    return true;
}

/**
 * Check whether a service category name is available in the database, optionally excluding the current form's `sc_id`.
 *
 * The provided name is sanitized before the existence check. If a form submit value `sc_id` is present, that ID
 * is excluded from the lookup to allow validating name uniqueness during edits.
 *
 * @param string|null $name The service category name to test.
 * @return bool `true` if the name is not present in the database (or only exists for the excluded `sc_id`), `false` otherwise.
 */
function testServiceCategorieExistence($name = null)
{
    global $pearDB, $form;

    $name = HtmlAnalyzer::sanitizeAndRemoveTags($name);
    $id = null;
    if (isset($form)) {
        $id = $form->getSubmitValue('sc_id');
    }
    $query = 'SELECT 1 FROM `service_categories` WHERE `sc_name` = :sc_name';
    if ($id !== null) {
        $query .= ' AND sc_id <> :scId';
    }
    $statement = $pearDB->prepare($query . ' LIMIT 1');
    $statement->bindValue(':sc_name', $name, PDO::PARAM_STR);
    if ($id !== null) {
        $statement->bindValue(':scId', (int) $id, PDO::PARAM_INT);
    }
    $statement->execute();

    return $statement->fetchColumn() === false;
}

/**
 * Evaluate a value's truthiness according to PHP and return it as a boolean.
 *
 * @param mixed $value The value to evaluate.
 * @return bool `true` if the value is truthy, `false` otherwise.
 */
function shouldNotBeEqTo0($value)
{
    return (bool) ($value);
}

/**
 * Create multiple duplicated service category records and replicate their service relations.
 *
 * This inserts new rows into service_categories by duplicating the provided source categories,
 * creates corresponding service_categories_relation entries for each new category, logs each creation,
 * and updates ACL entries for the duplicated categories.
 *
 * @param array $sc Mapping of source service category IDs to their rows (expected sc_id => row).
 * @param array $nbrDup Mapping of source service category IDs to the number of duplicates to create for each (sc_id => copies).
 *
 * @throws CentreonDbException If a database operation fails during duplication or relation insertion.
 */
function multipleServiceCategorieInDB($sc = [], $nbrDup = [])
{
    global $pearDB, $centreon;

    $scAcl = [];
    foreach ($sc as $key => $value) {
        $scId = filter_var($key, FILTER_VALIDATE_INT);
        $query = 'SELECT * FROM `service_categories` WHERE `sc_id` = :sc_id LIMIT 1';
        $statement = $pearDB->prepare($query);
        $statement->bindValue(':sc_id', $scId, PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch();
        $copies = filter_var($nbrDup[$scId] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
        if (! $copies) {
            continue;
        }
        $suffix = 1;
        for ($i = 0; $i < $copies && $suffix <= $copies + 1000; $suffix++) {
            $bindParams = [];
            $fields = [];
            foreach ($row as $key2 => $value2) {
                $value2 = is_int($value2) ? (string) $value2 : $value2;
                switch ($key2) {
                    case 'sc_name':
                        $value2 = HtmlAnalyzer::sanitizeAndRemoveTags($value2);
                        $sc_name = $value2 . '_' . $suffix;
                        $value2 = $value2 . '_' . $suffix;
                        $bindParams[':sc_name'] = [
                            PDO::PARAM_STR => $value2,
                        ];
                        break;
                    case 'sc_description':
                        $value2 = HtmlAnalyzer::sanitizeAndRemoveTags($value2);
                        $bindParams[':sc_description'] = [
                            PDO::PARAM_STR => $value2,
                        ];
                        break;
                    case 'level':
                        $value2 = filter_var($value2, FILTER_VALIDATE_INT);
                        $value2
                            ? $bindParams[':sc_level'] = [PDO::PARAM_INT => $value2]
                            : $bindParams[':sc_level'] = [PDO::PARAM_NULL => 'NULL'];
                        break;
                    case 'icon_id':
                        $value2 = filter_var($value2, FILTER_VALIDATE_INT);
                        $value2
                            ? $bindParams[':sc_icon_id'] = [PDO::PARAM_INT => $value2]
                            : $bindParams[':sc_icon_id'] = [PDO::PARAM_NULL => 'NULL'];
                        break;
                    case 'sc_activate':
                        $value2 = filter_var($value2, FILTER_VALIDATE_INT);
                        $value2
                            ? $bindParams[':sc_activate'] = [PDO::PARAM_STR => $value2]
                            : $bindParams[':sc_activate'] = [PDO::PARAM_STR =>  '0'];
                        break;
                }
                if ($key2 != 'sc_id') {
                    $fields[$key2] = $value2;
                }
            }
            if ($bindParams === []) {
                continue;
            }
            $fields['sc_name'] = $sc_name;
            if (! testServiceCategorieExistence($sc_name)) {
                continue;
            }
            $i++;
            $statement = $pearDB->prepare(
                <<<'SQL'
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
            $newScId = (int) $pearDB->lastInsertId();

            if ($newScId > 0) {
                $scAcl[$newScId] = $scId;
                try {
                    $selectServiceIdsStatement = $pearDB->prepareQuery(
                        <<<'SQL'
                                SELECT service_service_id FROM service_categories_relation
                                WHERE sc_id = :sc_id
                            SQL
                    );
                    $pearDB->executePreparedQuery($selectServiceIdsStatement, ['sc_id' => $scId]);
                    $insertNewRelationStatement = $pearDB->prepareQuery(
                        <<<'SQL'
                                INSERT INTO service_categories_relation (service_service_id, sc_id)
                                VALUES (:serviceId, :maxId)
                            SQL
                    );
                    $foundServiceIds = [];
                    while ($serviceId = $pearDB->fetchColumn($selectServiceIdsStatement)) {
                        $pearDB->executePreparedQuery($insertNewRelationStatement, [
                            'serviceId' => $serviceId,
                            'maxId' => $newScId,
                        ]);
                        $foundServiceIds[] = $serviceId;
                    }
                    if ($foundServiceIds !== []) {
                        $fields['sc_services'] = implode(', ', $foundServiceIds);
                    }

                    $centreon->CentreonLogAction->insertLog(
                        object_type: ActionLog::OBJECT_TYPE_SERVICECATEGORIES,
                        object_id: $newScId,
                        object_name: $sc_name,
                        action_type: ActionLog::ACTION_TYPE_ADD,
                        fields: $fields
                    );
                } catch (CentreonDbException $ex) {
                    CentreonLog::create()->error(
                        logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
                        message: 'Error while duplicating service categories: ' . $ex->getMessage(),
                        customContext: ['service_category_id' => $scId],
                        exception: $ex,
                    );

                    throw $ex;
                }
            }
        }
        if ($i < $copies) {
            error_log("Could only create {$i}/{$copies} duplicates for service category '{$row['sc_name']}' ({$scId}): suffix search exhausted");
        }
    }
    CentreonACL::duplicateScAcl($scAcl);
    $centreon->user->access->updateACL();
}

function enableServiceCategorieInDB(?int $serviceCategoryId = null, array $serviceCategories = [])
{

    if (! $serviceCategoryId && $serviceCategories === []) {
        return;
    }

    global $pearDB, $centreon;
    if ($serviceCategoryId) {
        $serviceCategories = [$serviceCategoryId => '1'];
    }

    try {
        $updateStatement = $pearDB->prepareQuery(
            <<<'SQL'
                    UPDATE service_categories
                    SET sc_activate = '1'
                    WHERE sc_id = :serviceCategoryId
                SQL
        );
        $selectStatement = $pearDB->prepareQuery(
            <<<'SQL'
                    SELECT sc_name FROM `service_categories`
                    WHERE `sc_id` = :serviceCategoryId LIMIT 1
                SQL
        );
        foreach (array_keys($serviceCategories) as $serviceCategoryId) {
            $pearDB->executePreparedQuery($updateStatement, ['serviceCategoryId' => $serviceCategoryId]);
            $pearDB->executePreparedQuery($selectStatement, ['serviceCategoryId' => $serviceCategoryId]);

            $result = $pearDB->fetch($selectStatement);
            $centreon->CentreonLogAction->insertLog(
                object_type: ActionLog::OBJECT_TYPE_SERVICECATEGORIES,
                object_id: $serviceCategoryId,
                object_name: $result['sc_name'],
                action_type: ActionLog::ACTION_TYPE_ENABLE
            );
        }
    } catch (CentreonDbException $ex) {
        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
            message: 'Error while enabling service category: ' . $ex->getMessage(),
            customContext: ['service_category_id' => $serviceCategoryId],
            exception: $ex,
        );

        throw $ex;
    }
}

function disableServiceCategorieInDB(?int $serviceCategoryId = null, array $serviceCategories = [])
{
    if (! $serviceCategoryId && $serviceCategories === []) {
        return;
    }

    global $pearDB, $centreon;
    if ($serviceCategoryId) {
        $serviceCategories = [$serviceCategoryId => '1'];
    }

    try {
        $updateStatement = $pearDB->prepareQuery(
            <<<'SQL'
                    UPDATE service_categories
                    SET sc_activate = '0'
                    WHERE sc_id = :serviceCategoryId
                SQL
        );
        $selectStatement = $pearDB->prepareQuery(
            <<<'SQL'
                    SELECT sc_name FROM `service_categories`
                    WHERE `sc_id` = :serviceCategoryId LIMIT 1
                SQL
        );
        foreach (array_keys($serviceCategories) as $serviceCategoryId) {
            $pearDB->executePreparedQuery($updateStatement, ['serviceCategoryId' => $serviceCategoryId]);
            $pearDB->executePreparedQuery($selectStatement, ['serviceCategoryId' => $serviceCategoryId]);

            $result = $pearDB->fetch($selectStatement);
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
            message: 'Error while disabling service category: ' . $ex->getMessage(),
            customContext: ['service_category_id' => implode(', ', array_keys($serviceCategories))],
            exception: $ex,
        );

        throw $ex;
    }
}

/**
 * Create a new service category from current form input, persist it, update related service relations and ACL, and record the creation in the action log.
 *
 * The function sanitizes submitted name and description, validates numeric fields, inserts a new row into the `service_categories` table when the name does not already exist, calls updateServiceCategoriesServices() for the new category, refreshes the user's ACL, and writes an ADD action to the Centreon action log referencing the newly created category.
 */
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
        PDO::PARAM_STR => $scName,
    ];
    $bindParams[':sc_description'] = [
        PDO::PARAM_STR => $scDescription,
    ];
    ($scSeverityLevel === false || $scType === false)
        ? $bindParams[':sc_severity_level'] = [PDO::PARAM_NULL => 'NULL']
        : $bindParams[':sc_severity_level'] = [PDO::PARAM_INT => $scSeverityLevel];

    ($scSeverityIconId === false || $scType === false)
        ? $bindParams[':sc_icon_id'] = [PDO::PARAM_NULL => 'NULL']
        : $bindParams[':sc_icon_id'] = [PDO::PARAM_INT => $scSeverityIconId];

    ($scActivate === false)
        ? $bindParams[':sc_activate'] = [PDO::PARAM_STR => '0']
        : $bindParams[':sc_activate'] = [PDO::PARAM_STR => $scActivate];
    if (testServiceCategorieExistence($scName)) {
        $query = '
            INSERT INTO `service_categories` (`sc_name`, `sc_description`, `level`, `icon_id`, `sc_activate`)
            VALUES (:sc_name, :sc_description, :sc_severity_level, :sc_icon_id, :sc_activate)';
        $statement = $pearDB->prepare($query);

        foreach ($bindParams as $token => $bindValues) {
            foreach ($bindValues as $paramType => $value) {
                $statement->bindValue($token, $value, $paramType);
            }
        }
        $statement->execute();

        $newScId = (int) $pearDB->lastInsertId();

        updateServiceCategoriesServices($newScId);
        $centreon->user->access->updateACL();
        $fields = CentreonLogAction::prepareChanges($formValues);
        $centreon->CentreonLogAction->insertLog(
            object_type: ActionLog::OBJECT_TYPE_SERVICECATEGORIES,
            object_id: $newScId,
            object_name: $scName,
            action_type: ActionLog::ACTION_TYPE_ADD,
            fields: $fields
        );
    }
}

/**
 * Update an existing service category in the database using submitted form values.
 *
 * Reads and sanitizes form input, updates the corresponding row in `service_categories`,
 * refreshes the service-category relations, updates the user's ACL, and records the change in the action log.
 */
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
        PDO::PARAM_INT => $scId,
    ];
    $bindParams[':sc_name'] = [
        PDO::PARAM_STR => $scName,
    ];
    $bindParams[':sc_description'] = [
        PDO::PARAM_STR => $scDescription,
    ];
    ($scSeverityLevel === false || $scType === false)
        ? $bindParams[':sc_severity_level'] = [PDO::PARAM_NULL => 'NULL']
        : $bindParams[':sc_severity_level'] = [PDO::PARAM_INT => $scSeverityLevel];

    ($scSeverityIconId === false || $scType === false)
        ? $bindParams[':sc_icon_id'] = [PDO::PARAM_NULL => 'NULL']
        : $bindParams[':sc_icon_id'] = [PDO::PARAM_INT => $scSeverityIconId];

    ($scActivate === false)
        ? $bindParams[':sc_activate'] = [PDO::PARAM_STR => '0']
        : $bindParams[':sc_activate'] = [PDO::PARAM_STR => $scActivate];

    $query = '
        UPDATE `service_categories`
        SET `sc_name` = :sc_name,
            `sc_description` = :sc_description,
            `level` = :sc_severity_level,
            `icon_id` = :sc_icon_id,
            `sc_activate` = :sc_activate
        WHERE `sc_id` = :sc_id';
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

/**
 * Delete one or more service categories by id.
 *
 * Deletes each provided service category, logs a delete action for each removed category,
 * and refreshes the user ACL. Non-existent ids are skipped; if $serviceCategoryIds is null no action is taken.
 *
 * @param array|null $serviceCategoryIds Array whose keys are treated as service category ids to delete.
 * @throws Exception If a provided id is not a valid integer.
 * @throws CentreonDbException If a database error occurs during deletion.
 */
function deleteServiceCategorieInDB($serviceCategoryIds = null)
{
    global $pearDB, $centreon;

    if (is_null($serviceCategoryIds)) {
        return;
    }

    try {
        $deleteStatement = $pearDB->prepareQuery(
            <<<'SQL'
                    DELETE FROM `service_categories`
                    WHERE `sc_id` = :sc_id
                SQL
        );
        $selectStatement = $pearDB->prepareQuery(
            <<<'SQL'
                    SELECT sc_name FROM `service_categories`
                    WHERE `sc_id` = :serviceCategoryId LIMIT 1
                SQL
        );
        foreach (array_keys($serviceCategoryIds) as $serviceCategoryId) {
            $serviceCategoryId = filter_var($serviceCategoryId, FILTER_VALIDATE_INT)
                ?: throw new Exception('Invalid service category id');

            $pearDB->executePreparedQuery($selectStatement, ['serviceCategoryId' => $serviceCategoryId]);
            $result = $pearDB->fetch($selectStatement);
            if ($result === false) {
                continue;
            }
            $pearDB->executePreparedQuery($deleteStatement, ['sc_id' => $serviceCategoryId]);
            $centreon->CentreonLogAction->insertLog(
                object_type: ActionLog::OBJECT_TYPE_SERVICECATEGORIES,
                object_id: $serviceCategoryId,
                object_name: $result['sc_name'],
                action_type: ActionLog::ACTION_TYPE_DELETE
            );
        }
        $centreon->user->access->updateACL();
    } catch (CentreonDbException $ex) {
        CentreonLog::create()->error(
            logTypeId: CentreonLog::TYPE_BUSINESS_LOG,
            message: 'Error while deleting service categories: ' . $ex->getMessage(),
            customContext: ['service_category_id' => implode(', ', $serviceCategoryIds)],
            exception: $ex,
        );

        throw $ex;
    }
}

function updateServiceCategoriesServices(int $sc_id)
{
    global $pearDB, $form;

    if (! $sc_id) {
        return;
    }
    $query = "
        DELETE FROM service_categories_relation WHERE sc_id = :sc_id
        AND service_service_id IN (SELECT service_id FROM service WHERE service_register = '0')";
    $statement = $pearDB->prepare($query);
    $statement->bindValue(':sc_id', $sc_id, PDO::PARAM_INT);
    $statement->execute();
    if (isset($_POST['sc_svcTpl'])) {
        foreach ($_POST['sc_svcTpl'] as $serviceId) {
            $serviceId = filter_var($serviceId, FILTER_VALIDATE_INT);
            $query = '
                INSERT INTO service_categories_relation (service_service_id, sc_id)
                VALUES (:service_id, :sc_id)';
            $statement = $pearDB->prepare($query);
            $statement->bindValue(':service_id', $serviceId, PDO::PARAM_INT);
            $statement->bindValue(':sc_id', $sc_id, PDO::PARAM_INT);
            $statement->execute();
        }
    }
}
