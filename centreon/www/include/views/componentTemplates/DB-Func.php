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

    $query = 'SELECT compo_id FROM giv_components_template WHERE ds_name = :ds_name';

    if (! empty($formValues['host_id'])) {
        if (preg_match('/([0-9]+)-([0-9]+)/', $formValues['host_id'], $matches)) {
            $formValues['host_id'] = (int) $matches[1];
            $formValues['service_id'] = (int) $matches[2];
        } else {
            throw new InvalidArgumentException('host_id must be a combination of integers');
        }
    }

    if (! empty($formValues['host_id']) && ! empty($formValues['service_id'])) {
        $query .= ' AND host_id = :hostId AND service_id = :serviceId';
        $hostId = (filter_var($formValues['host_id'], FILTER_VALIDATE_INT) === false)
            ? null
            : (int) $formValues['host_id'];
        $serviceId = (filter_var($formValues['service_id'], FILTER_VALIDATE_INT) === false)
            ? null
            : (int) $formValues['service_id'];
    } else {
        $query .= ' AND host_id IS NULL AND service_id IS NULL';
    }

    $stmt = $pearDB->prepare($query);

    $stmt->bindValue(':ds_name', $name, PDO::PARAM_STR);

    if (! empty($hostId) && ! empty($serviceId)) {
        $stmt->bindValue(':hostId', $hostId, PDO::PARAM_INT);
        $stmt->bindValue(':serviceId', $serviceId, PDO::PARAM_INT);
    }

    $stmt->execute();
    $compo = $stmt->fetch();
    if ($stmt->rowCount() >= 1 && $compo['compo_id'] === (int) $formValues['compo_id']) {
        return true;
    }

    return ! ($stmt->rowCount() >= 1 && $compo['compo_id'] !== (int) $formValues['compo_id']);
}

function NameHsrTestExistence($name = null)
{
    global $pearDB, $form;
    $formValues = [];

    if (isset($form)) {
        $formValues = $form->getSubmitValues();
    }
    $query = 'SELECT compo_id FROM giv_components_template WHERE name = :name';
    if (! empty($formValues['host_id'])) {
        if (preg_match('/([0-9]+)-([0-9]+)/', $formValues['host_id'], $matches)) {
            $formValues['host_id'] = (int) $matches[1];
            $formValues['service_id'] = (int) $matches[2];
        } else {
            throw new InvalidArgumentException('chartId must be a combination of integers');
        }
    }

    if (! empty($formValues['host_id']) && ! empty($formValues['service_id'])) {
        $query .= ' AND host_id = :hostId AND service_id = :serviceId';
        $hostId = (filter_var($formValues['host_id'], FILTER_VALIDATE_INT) === false)
            ? null
            : (int) $formValues['host_id'];
        $serviceId = (filter_var($formValues['service_id'], FILTER_VALIDATE_INT) === false)
            ? null
            : (int) $formValues['service_id'];
    } else {
        $query .= ' AND host_id IS NULL  AND service_id IS NULL';
    }

    $stmt = $pearDB->prepare($query);

    $stmt->bindValue(':name', $name, PDO::PARAM_STR);

    if (! empty($hostId) && ! empty($serviceId)) {
        $stmt->bindValue(':hostId', $hostId, PDO::PARAM_INT);
        $stmt->bindValue(':serviceId', $serviceId, PDO::PARAM_INT);
    }

    $stmt->execute();
    $compo = $stmt->fetch();
    if ($stmt->rowCount() >= 1 && $compo['compo_id'] === (int) $formValues['compo_id']) {
        return true;
    }

    return ! ($stmt->rowCount() >= 1 && $compo['compo_id'] !== (int) $formValues['compo_id']);
}

function checkColorFormat($color)
{
    return ! ($color != '' && strncmp($color, '#', 1));
}

/**
 * DELETE components in the database
 *
 * @param array $compos
 * @return void
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

function defaultOreonGraph()
{
    global $pearDB;
    $dbResult = $pearDB->query("SELECT DISTINCT compo_id FROM giv_components_template WHERE default_tpl1 = '1'");
    if (! $dbResult->rowCount()) {
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
    foreach ($compos as $key => $value) {
        $stmt = $pearDB->prepare(
            'SELECT * FROM giv_components_template WHERE compo_id = :compo_id LIMIT 1'
        );
        $stmt->bindValue(':compo_id', $key, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        $row['compo_id'] = '';
        $row['default_tpl1'] = '0';
        for ($i = 1; $i <= $nbrDup[$key]; $i++) {
            $val = null;
            foreach ($row as $key2 => $value2) {
                $value2 = is_int($value2) ? (string) $value2 : $value2;
                if ($key2 == 'name') {
                    $name = $value2 . '_' . $i;
                    $value2 = $value2 . '_' . $i;
                }
                $val ? $val .= ($value2 != null ? (", '" . $value2 . "'") : ', NULL')
                    : $val .= ($value2 != null ? ("'" . $value2 . "'") : 'NULL');
            }
            if (NameHsrTestExistence($name)) {
                $rq = $val ? 'INSERT INTO giv_components_template VALUES (' . $val . ')' : null;
                $pearDB->query($rq);
            }
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
    $formValues = [];
    $formValues = $form->getSubmitValues();

    if (
        (isset($formValues['ds_filled']) && $formValues['ds_filled'] === '1')
        && (! isset($formValues['ds_color_area']) || empty($formValues['ds_color_area']))
    ) {
        $formValues['ds_color_area'] = $formValues['ds_color_line'];
    }

    [$formValues['host_id'], $formValues['service_id']] = parseHostIdPostParameter($formValues['host_id']);

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
    defaultOreonGraph();
    $result = $pearDB->query('SELECT MAX(compo_id) FROM giv_components_template');
    $compoId = $result->fetch();

    return $compoId['MAX(compo_id)'];
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
    if (! empty($hostIdParameter)) {
        if (preg_match('/([0-9]+)-([0-9]+)/', $hostIdParameter, $matches)) {
            $hostId = (int) $matches[1];
            $serviceId = (int) $matches[2];
        } else {
            throw new InvalidArgumentException('host_id must be a combination of integers');
        }
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
    $formValues = [];
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
                $bindParams[':' . $inputName] = [
                    PDO::PARAM_INT, (filter_var($inputValue, FILTER_VALIDATE_INT) === false)
                        ? null
                        : (int) $inputValue,
                ];
                defaultOreonGraph();
                break;
        }
    }

    return $bindParams;
}
