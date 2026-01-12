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

function testMnftrExistence($name = null)
{
    global $pearDB;
    global $form;
    $id = null;
    if (isset($form)) {
        $id = $form->getSubmitValue('id');
    }
    $query = "SELECT name, id FROM traps_vendor WHERE name = '" . htmlentities($name, ENT_QUOTES, 'UTF-8') . "'";
    $dbResult = $pearDB->query($query);
    $mnftr = $dbResult->fetch();
    // Modif case
    if ($dbResult->rowCount() >= 1 && $mnftr['id'] == $id) {
        return true;
    } // Duplicate entry

    return ! ($dbResult->rowCount() >= 1 && $mnftr['id'] != $id);
}

function deleteMnftrInDB($mnftr = [])
{
    global $pearDB, $oreon;
    foreach ($mnftr as $key => $value) {
        $dbResult2 = $pearDB->query("SELECT name FROM `traps_vendor` WHERE `id` = '" . $key . "' LIMIT 1");
        $row = $dbResult2->fetch();

        $pearDB->query("DELETE FROM traps_vendor WHERE id = '" . htmlentities($key, ENT_QUOTES, 'UTF-8') . "'");
        $oreon->CentreonLogAction->insertLog('manufacturer', $key, $row['name'], 'd');
    }
}

function multipleMnftrInDB($mnftr = [], $nbrDup = [])
{
    foreach ($mnftr as $key => $value) {
        global $pearDB, $oreon;
        $query = "SELECT * FROM traps_vendor WHERE id = '" . htmlentities($key, ENT_QUOTES, 'UTF-8') . "' LIMIT 1";
        $dbResult = $pearDB->query($query);
        $row = $dbResult->fetch();
        $row['id'] = null;
        for ($i = 1; $i <= $nbrDup[$key]; $i++) {
            $val = null;
            foreach ($row as $key2 => $value2) {
                $value2 = is_int($value2) ? (string) $value2 : $value2;
                $name = '';
                if ($key2 == 'name') {
                    $name = $value2 . '_' . $i;
                    $value2 = $value2 . '_' . $i;
                }
                $val
                    ? $val .= ($value2 != null ? (", '" . $value2 . "'") : ', NULL')
                    : $val .= ($value2 != null ? ("'" . $value2 . "'") : 'NULL');
                if ($key2 != 'id') {
                    $fields[$key2] = $value2;
                }
                $fields['name'] = $name;
            }
            if (testMnftrExistence($name)) {
                $rq = $val ? 'INSERT INTO traps_vendor VALUES (' . $val . ')' : null;
                $pearDB->query($rq);
                $oreon->CentreonLogAction->insertLog(
                    'manufacturer',
                    htmlentities($key, ENT_QUOTES, 'UTF-8'),
                    $name,
                    'a',
                    $fields
                );
            }
        }
    }
}

function updateMnftrInDB($id = null)
{
    if (! $id) {
        return;
    }
    updateMnftr($id);
}

function updateMnftr($id = null)
{
    global $form, $pearDB, $oreon;

    if (! $id) {
        return;
    }

    $ret = [];
    $ret = $form->getSubmitValues();
    $rq = 'UPDATE traps_vendor ';
    $rq .= "SET name = '" . htmlentities($ret['name'], ENT_QUOTES, 'UTF-8') . "', ";
    $rq .= "alias = '" . htmlentities($ret['alias'], ENT_QUOTES, 'UTF-8') . "', ";
    $rq .= "description = '" . htmlentities($ret['description'], ENT_QUOTES, 'UTF-8') . "' ";
    $rq .= "WHERE id = '" . $id . "'";
    $dbResult = $pearDB->query($rq);

    // Prepare value for changelog
    $fields = CentreonLogAction::prepareChanges($ret);
    $oreon->CentreonLogAction->insertLog('manufacturer', $id, $fields['name'], 'c', $fields);
}

function insertMnftrInDB($ret = [])
{
    return insertMnftr($ret);
}

function insertMnftr($ret = [])
{
    global $form, $pearDB, $oreon;

    if (! count($ret)) {
        $ret = $form->getSubmitValues();
    }

    $rq = 'INSERT INTO traps_vendor ';
    $rq .= '(name, alias, description) ';
    $rq .= 'VALUES ';
    $rq .= "('" . htmlentities($ret['name'], ENT_QUOTES, 'UTF-8') . "', ";
    $rq .= "'" . htmlentities($ret['alias'], ENT_QUOTES, 'UTF-8') . "', ";
    $rq .= "'" . htmlentities($ret['description'], ENT_QUOTES, 'UTF-8') . "')";
    $dbResult = $pearDB->query($rq);
    $dbResult = $pearDB->query('SELECT MAX(id) FROM traps_vendor');
    $mnftr_id = $dbResult->fetch();

    // Prepare value for changelog
    $fields = CentreonLogAction::prepareChanges($ret);
    $oreon->CentreonLogAction->insertLog('manufacturer', $mnftr_id['MAX(id)'], $fields['name'], 'a', $fields);

    return $mnftr_id['MAX(id)'];
}
