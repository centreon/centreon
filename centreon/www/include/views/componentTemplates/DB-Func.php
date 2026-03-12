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

function checkColorFormat($color)
{
    return ! ($color != '' && strncmp($color, '#', 1));
}

/**
 * DELETE components in the database
 *
 * @param array<int, mixed> $compos component IDs as keys
 * @return void
 */
function deleteComponentTemplateInDB($compos = [])
{
    global $pearDB;
    $validIds = array_filter(
        array_map(
            static fn ($key) => filter_var($key, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]),
            array_keys($compos)
        ),
        static fn ($id) => $id !== false
    );

    if ($validIds === []) {
        return;
    }

    $placeholders = [];
    foreach (array_values($validIds) as $idx => $id) {
        $placeholders[] = ':key_' . $idx;
    }

    $stmt = $pearDB->prepare(
        'DELETE FROM giv_components_template WHERE compo_id IN (' . implode(', ', $placeholders) . ')'
    );

    foreach (array_values($validIds) as $idx => $id) {
        $stmt->bindValue(':key_' . $idx, $id, PDO::PARAM_INT);
    }

    $stmt->execute();
    defaultOreonGraph();
}

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
        if ($dupCount === false || $dupCount === 0) {
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
    $compoId = (int) $pearDB->lastInsertId();
    if ($compoId <= 0) {
        throw new RuntimeException('Failed to retrieve inserted component template id');
    }
    defaultOreonGraph();

    return $compoId;
}

/**
 * Parses the host_id parameter from the form and checks the hostId-serviceId format
 * and returns the hostId et serviceId when defined.
 *
 * @param string|null $hostIdParameter
 * @return array
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
 * Sanitize all the component templates parameters from the component template form
 * and return a ready to bind array.
 *
 * @param array $ret
 * @return array $bindParams
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
                    PDO::PARAM_STR, in_array($inputValue, ['0', '1'])
                        ? $inputValue
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
