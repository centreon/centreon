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

if (! isset($centreon)) {
    exit();
}

/**
 * Checks whether a datasource name is available for a component template within the current host/service scope.
 *
 * If a submitted form provides a `host_service_id`, that value is parsed and the check is scoped to that host and service;
 * otherwise the check is performed against templates with NULL host_id and service_id. If the submitted form includes
 * `compo_id`, that component ID is excluded from the existence check (useful to ignore the current row during updates).
 *
 * @param string|null $name The datasource name (`ds_name`) to check.
 * @return bool `true` if no matching component template exists in the resolved scope (name is available), `false` otherwise.
 */
function DsHsrTestExistence($name = null)
{
    global $pearDB, $form;
    $formValues = [];
    if (isset($form)) {
        $formValues = $form->getSubmitValues();
    }

    $query = 'SELECT 1 FROM giv_components_template WHERE ds_name = :ds_name';

    [$hostId, $serviceId] = parseHostIdPostParameter($formValues['host_service_id'] ?? null);

    if ($hostId !== null && $serviceId !== null) {
        $query .= ' AND host_id = :hostId AND service_id = :serviceId';
    } else {
        $query .= ' AND host_id IS NULL AND service_id IS NULL';
    }

    $compoId = isset($formValues['compo_id']) ? (int) $formValues['compo_id'] : null;
    if ($compoId !== null) {
        $query .= ' AND compo_id <> :compoId';
    }

    $stmt = $pearDB->prepare($query . ' LIMIT 1');
    $stmt->bindValue(':ds_name', $name, PDO::PARAM_STR);

    if ($hostId !== null && $serviceId !== null) {
        $stmt->bindValue(':hostId', $hostId, PDO::PARAM_INT);
        $stmt->bindValue(':serviceId', $serviceId, PDO::PARAM_INT);
    }
    if ($compoId !== null) {
        $stmt->bindValue(':compoId', $compoId, PDO::PARAM_INT);
    }

    $stmt->execute();

    return $stmt->fetchColumn() === false;
}

/**
 * Checks whether a component template name is available within an optional host/service scope.
 *
 * If both `$hostId` and `$serviceId` are null, host and service IDs are derived from submitted form values.
 *
 * @param string|null $name The component template name to check.
 * @param int|null $hostId Optional host ID to scope the lookup; provide both host and service to apply scoped check.
 * @param int|null $serviceId Optional service ID to scope the lookup; provide both host and service to apply scoped check.
 * @return bool `true` if no component template with the given name exists in the specified scope, `false` otherwise.
 */
function NameHsrTestExistence($name = null, ?int $hostId = null, ?int $serviceId = null)
{
    global $pearDB, $form;
    $formValues = [];
    $compoId = null;

    if (isset($form)) {
        $formValues = $form->getSubmitValues();
    }

    // When called from the duplication path, host/service are passed explicitly.
    // When called as a form validator, derive them from the submitted form values.
    if ($hostId === null && $serviceId === null) {
        [$hostId, $serviceId] = parseHostIdPostParameter($formValues['host_service_id'] ?? null);
        $compoId = isset($formValues['compo_id']) ? (int) $formValues['compo_id'] : null;
    }

    $query = 'SELECT 1 FROM giv_components_template WHERE name = :name';

    if ($hostId !== null && $serviceId !== null) {
        $query .= ' AND host_id = :hostId AND service_id = :serviceId';
    } else {
        $query .= ' AND host_id IS NULL AND service_id IS NULL';
    }

    if ($compoId !== null) {
        $query .= ' AND compo_id <> :compoId';
    }

    $stmt = $pearDB->prepare($query . ' LIMIT 1');
    $stmt->bindValue(':name', $name, PDO::PARAM_STR);

    if ($hostId !== null && $serviceId !== null) {
        $stmt->bindValue(':hostId', $hostId, PDO::PARAM_INT);
        $stmt->bindValue(':serviceId', $serviceId, PDO::PARAM_INT);
    }
    if ($compoId !== null) {
        $stmt->bindValue(':compoId', $compoId, PDO::PARAM_INT);
    }

    $stmt->execute();

    return $stmt->fetchColumn() === false;
}

/**
 * Determines whether a color value is empty or begins with a '#' character.
 *
 * @param string $color The color string to validate.
 * @return bool `true` if `$color` is empty or starts with `#`, `false` otherwise.
 */
function checkColorFormat($color)
{
    return ! ($color != '' && strncmp($color, '#', 1));
}

/**
 * Delete component templates identified by the array's keys.
 *
 * The array's keys are treated as component IDs to delete; array values are ignored.
 * After deletion, ensures a default component template exists by invoking defaultOreonGraph().
 *
 * @param array<int,mixed> $compos Array whose keys are compo_id values to remove.
 */
function deleteComponentTemplateInDB($compos = [])
{
    global $pearDB;
    $query = 'DELETE FROM giv_components_template WHERE compo_id IN (';

    foreach (array_keys($compos) as $compoId) {
        $query .= ':key_' . $compoId . ', ';
    }
    $query = rtrim($query, ', ');
    $query .= ')';

    $stmt = $pearDB->prepare($query);

    foreach (array_keys($compos) as $compoId) {
        $stmt->bindValue(':key_' . $compoId, $compoId, PDO::PARAM_INT);
    }

    $stmt->execute();
    defaultOreonGraph();
}

/**
 * Ensures at least one component template is marked as the default Oreon graph.
 *
 * If no row in giv_components_template has default_tpl1 = '1', this function sets default_tpl1 = '1' on a single row.
 */
function defaultOreonGraph()
{
    global $pearDB;
    $dbResult = $pearDB->query("SELECT DISTINCT compo_id FROM giv_components_template WHERE default_tpl1 = '1' LIMIT 1");
    if ($dbResult->fetch() === false) {
        $dbResult2 = $pearDB->query("UPDATE giv_components_template SET default_tpl1 = '1' LIMIT 1");
    }
}

function noDefaultOreonGraph()
{
    global $pearDB;
    $rq = "UPDATE giv_components_template SET default_tpl1 = '0'";
    $pearDB->query($rq);
}

/**
 * Creates multiple duplicates of existing component templates identified by their IDs.
 *
 * For each component ID provided as a key in $compos, this function clones the template row
 * up to the number of duplicates specified in $nbrDup for that key. Each duplicate uses the
 * original row's columns (except `compo_id`) with `default_tpl1` set to '0' and a name
 * suffixed with "_<n>" where n increments until a non-colliding name is found. If a requested
 * duplicate name already exists in the same host/service scope it is skipped. If the function
 * cannot create the requested number of duplicates after a large number of suffix attempts,
 * it logs a partial-success error.
 *
 * @param array $compos Associative array whose keys are component template IDs to duplicate (values are ignored).
 * @param array $nbrDup Associative array mapping the same keys as $compos to the desired number of duplicates (0..100).
 */
function multipleComponentTemplateInDB($compos = [], $nbrDup = [])
{
    global $pearDB;

    if (empty($compos) || empty($nbrDup)) {
        return;
    }

    $selectStmt = $pearDB->prepare(
        'SELECT * FROM giv_components_template WHERE compo_id = :compo_id LIMIT 1'
    );

    foreach (array_keys($compos) as $key) {
        $compoId = filter_var($key, FILTER_VALIDATE_INT);
        if ($compoId === false) {
            continue;
        }

        $dupCount = filter_var($nbrDup[$key] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
        if ($dupCount === false) {
            continue;
        }
        $selectStmt->bindValue(':compo_id', $compoId, PDO::PARAM_INT);
        $selectStmt->execute();
        $row = $selectStmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            continue;
        }

        unset($row['compo_id']);
        $row['default_tpl1'] = '0';
        $columns = array_keys($row);
        $placeholders = implode(', ', array_map(fn ($col) => ':' . $col, $columns));
        $insertStmt = $pearDB->prepare(
            'INSERT INTO giv_components_template (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')'
        );

        $originalName = $row['name'];
        $suffix = 1;
        for ($i = 0; $i < $dupCount && $suffix <= $dupCount + 1000; $suffix++) {
            $row['name'] = $originalName . '_' . $suffix;
            if (! NameHsrTestExistence(
                $row['name'],
                $row['host_id'] !== null ? (int) $row['host_id'] : null,
                $row['service_id'] !== null ? (int) $row['service_id'] : null
            )) {
                continue;
            }
            $i++;
            foreach ($columns as $col) {
                $value = $row[$col];
                $insertStmt->bindValue(':' . $col, $value, $value === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            }
            $insertStmt->execute();
        }
        if ($i < $dupCount) {
            error_log("Could only create {$i}/{$dupCount} duplicates for component template '{$originalName}' ({$key}): suffix search exhausted");
        }
    }
}

function updateComponentTemplateInDB($compoId = null)
{
    if (! $compoId) {
        return;
    }
    updateComponentTemplate($compoId);
}

function insertComponentTemplateInDB()
{
    return insertComponentTemplate();
}

/**
 * Insert a new component template using submitted form values.
 *
 * Reads form submission from the global form, normalizes host/service IDs, sanitizes fields,
 * inserts a new row into giv_components_template, ensures a default template exists, and
 * returns the new component template identifier.
 *
 * @return int|string The ID of the newly created component template.
 */
function insertComponentTemplate()
{
    global $form, $pearDB;
    $formValues = $form->getSubmitValues();

    if (
        (isset($formValues['ds_filled']) && $formValues['ds_filled'] === '1')
        && (! isset($formValues['ds_color_area']) || empty($formValues['ds_color_area']))
    ) {
        $formValues['ds_color_area'] = $formValues['ds_color_line'];
    }

    [$formValues['host_id'], $formValues['service_id']] = parseHostIdPostParameter($formValues['host_service_id']);

    $bindParams = sanitizeFormComponentTemplatesParameters($formValues);

    $params = [];
    foreach (array_keys($bindParams) as $token) {
        $params[] = ltrim($token, ':');
    }

    $query = 'INSERT INTO `giv_components_template` (`compo_id`, ';
    $query .= implode(', ', $params) . ') ';

    $query .= 'VALUES (NULL, ' . implode(', ', array_keys($bindParams)) . ')';
    $stmt = $pearDB->prepare($query);
    foreach ($bindParams as $token => [$paramType, $value]) {
        $stmt->bindValue($token, $value, $paramType);
    }
    $stmt->execute();
    $compoId = $pearDB->lastInsertId();
    defaultOreonGraph();

    return $compoId;
}

/**
 * Extracts host_id and service_id from a form parameter formatted as "hostId-serviceId".
 *
 * @param string|null $hostIdParameter Form parameter expected in the format "hostId-serviceId" (e.g., "12-34").
 * @return array Array with two elements: hostId and serviceId; each is an int when present or null when not.
 */
function parseHostIdPostParameter(?string $hostIdParameter): array
{
    if (! empty($hostIdParameter)
        && preg_match('/^([0-9]+)-([0-9]+)$/', $hostIdParameter, $matches)
    ) {
        $hostId = (int) $matches[1];
        $serviceId = (int) $matches[2];
    } else {
        $hostId = null;
        $serviceId = null;
    }

    return [$hostId, $serviceId];
}

/**
 * Update a component template in the database from submitted form values.
 *
 * Reads submitted form values, applies normalization and sanitization, updates the database row for the specified component template, and ensures default template state is maintained.
 *
 * @param int|null $compoId The ID of the component template to update; if null or falsy the function does nothing.
 */
function updateComponentTemplate($compoId = null)
{
    if (! $compoId) {
        return;
    }
    global $form, $pearDB;
    $formValues = $form->getSubmitValues();

    if (
        (array_key_exists('ds_filled', $formValues) && $formValues['ds_filled'] === '1')
        && (! array_key_exists('ds_color_area', $formValues) || empty($formValues['ds_color_area']))
    ) {
        $formValues['ds_color_area'] = $formValues['ds_color_line'];
    }

    [$formValues['host_id'], $formValues['service_id']] = parseHostIdPostParameter($formValues['host_service_id']);

    // Sets the default values if they have not been sent (used to deselect the checkboxes)
    $checkBoxValueToSet = [
        'ds_stack',
        'ds_invert',
        'ds_filled',
        'ds_hidecurve',
        'ds_max',
        'ds_min',
        'ds_minmax_int',
        'ds_average',
        'ds_last',
        'ds_total',
    ];
    foreach ($checkBoxValueToSet as $element) {
        $formValues[$element] ??= '0';
    }

    $bindParams = sanitizeFormComponentTemplatesParameters($formValues);

    $query = 'UPDATE `giv_components_template` SET ';

    foreach (array_keys($bindParams) as $token) {
        $query .= ltrim($token, ':') . ' = ' . $token . ', ';
    }

    $query = rtrim($query, ', ');
    $query .= ' WHERE compo_id = :compo_id';

    $stmt = $pearDB->prepare($query);
    foreach ($bindParams as $token => [$paramType, $value]) {
        $stmt->bindValue($token, $value, $paramType);
    }
    $stmt->bindValue(':compo_id', $compoId, PDO::PARAM_INT);
    $stmt->execute();

    defaultOreonGraph();
}

/**
 * Prepare and sanitize component template form values for safe PDO binding.
 *
 * Sanitizes string inputs, normalizes enum/boolean-like fields to valid string values,
 * converts integer fields to ints or null, and returns an associative array of
 * PDO parameter placeholders to pairs of (PDO::PARAM_*, value) suitable for bind operations.
 *
 * If the `default_tpl1` field is present, this function invokes defaultOreonGraph() to
 * ensure at least one template is marked as the default.
 *
 * @param array $ret Associative array of form input names to submitted values.
 * @return array<string, array> Map of parameter placeholders (e.g. `:name`) to `[PDO::PARAM_*, value]`.
 */
function sanitizeFormComponentTemplatesParameters(array $ret): array
{
    $bindParams = [];
    foreach ($ret as $inputName => $inputValue) {
        switch ($inputName) {
            case 'name':
            case 'ds_name':
            case 'ds_color_line':
            case 'ds_color_area':
            case 'ds_color_area_warn':
            case 'ds_color_area_crit':
            case 'ds_legend':
            case 'comment':
            case 'ds_transparency':
                if (! empty($inputValue)) {
                    $inputValue = HtmlAnalyzer::sanitizeAndRemoveTags($inputValue);
                    $bindParams[':' . $inputName] = empty($inputValue) ? [PDO::PARAM_STR, null] : [PDO::PARAM_STR, $inputValue];
                }
                break;
            case 'ds_color_line_mode':
                $bindParams[':' . $inputName] = [
                    PDO::PARAM_STR, in_array($inputValue[$inputName], ['0', '1'])
                        ? $inputValue[$inputName]
                        : '0',
                ];
                break;
            case 'ds_max':
            case 'ds_min':
            case 'ds_minmax_int':
                $bindParams[':' . $inputName] = [
                    PDO::PARAM_STR, in_array($inputValue, ['0', '1'])
                        ? $inputValue
                        : null,
                ];
                break;
            case 'ds_average':
            case 'ds_last':
            case 'ds_total':
            case 'ds_stack':
            case 'ds_invert':
            case 'ds_filled':
            case 'ds_hidecurve':
                $bindParams[':' . $inputName] = [
                    PDO::PARAM_STR, in_array($inputValue, ['0', '1'])
                        ? $inputValue
                        : '0',
                ];
                break;
            case 'ds_jumpline':
                $bindParams[':' . $inputName] = [
                    PDO::PARAM_STR, in_array($inputValue, ['0', '1', '2', '3'])
                        ? $inputValue
                        : '0',
                ];
                break;
            case 'host_id':
            case 'service_id':
            case 'ds_tickness':
            case 'ds_order':
                $bindParams[':' . $inputName] = [
                    PDO::PARAM_INT, (filter_var($inputValue, FILTER_VALIDATE_INT) === false)
                        ? null
                        : (int) $inputValue,
                ];
                break;
            case 'default_tpl1':
                // default_tpl1 is enum('0','1')
                $bindParams[':' . $inputName] = [
                    PDO::PARAM_STR, in_array((string) $inputValue, ['0', '1'], true)
                        ? (string) $inputValue
                        : '0',
                ];
                defaultOreonGraph();
                break;
        }
    }

    return $bindParams;
}
