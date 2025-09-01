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

function testTrapGroupExistence($name = null)
{
    global $pearDB, $form;
    $id = null;

    if (isset($form)) {
        $id = $form->getSubmitValue('id');
    }
    $query = "SELECT traps_group_id as id FROM traps_group WHERE traps_group_name = '"
        . $pearDB->escape(htmlentities($name, ENT_QUOTES, 'UTF-8')) . "'";
    $dbResult = $pearDB->query($query);
    $trap_group = $dbResult->fetch();
    // Modif case
    if ($dbResult->rowCount() >= 1 && $trap_group['id'] == $id) {
        return true;
    } // Duplicate entry

    return ! ($dbResult->rowCount() >= 1 && $trap_group['id'] != $id);
}

function deleteTrapGroupInDB($trap_groups = [])
{
    global $pearDB, $oreon;

    foreach ($trap_groups as $key => $value) {
        $query = "SELECT traps_group_name as name FROM `traps_group` WHERE `traps_group_id` = '"
            . $pearDB->escape($key) . "' LIMIT 1";
        $dbResult2 = $pearDB->query($query);
        $row = $dbResult2->fetch();

        $pearDB->query("DELETE FROM traps_group WHERE traps_group_id = '" . $pearDB->escape($key) . "'");
        $oreon->CentreonLogAction->insertLog('traps_group', $key, $row['name'], 'd');
    }
}

function multipleTrapGroupInDB($trap_groups = [], $nbrDup = [])
{
    global $pearDB, $oreon;

    foreach ($trap_groups as $key => $value) {
        $query = "SELECT * FROM traps_group WHERE traps_group_id = '" . $pearDB->escape($key) . "' LIMIT 1";
        $dbResult = $pearDB->query($query);
        $row = $dbResult->fetch();
        $row['traps_group_id'] = null;

        for ($i = 1; $i <= $nbrDup[$key]; $i++) {
            $val = null;
            foreach ($row as $key2 => $value2) {
                $value2 = is_int($value2) ? (string) $value2 : $value2;
                $name = '';
                if ($key2 == 'traps_group_name') {
                    $name = $value2 . '_' . $i;
                    $value2 = $value2 . '_' . $i;
                }
                $val
                    ? $val .= ($value2 != null ? (", '" . $value2 . "'") : ', NULL')
                    : $val .= ($value2 != null ? ("'" . $value2 . "'") : 'NULL');
                if ($key2 != 'traps_group_id') {
                    $fields[$key2] = $value2;
                }
                $fields['name'] = $name;
            }

            if (testTrapGroupExistence($name)) {
                $rq = $val ? 'INSERT INTO traps_group VALUES (' . $val . ')' : null;
                $pearDB->query($rq);
                $oreon->CentreonLogAction->insertLog('traps_group', $key, $name, 'a', $fields);

                $query = 'INSERT INTO traps_group_relation (traps_group_id, traps_id) SELECT '
                    . '(SELECT MAX(traps_group_id) as max_id FROM traps_group), traps_id FROM traps_group_relation '
                    . "WHERE traps_group_id = '" . $pearDB->escape($key) . "'";
                $pearDB->query($query);
            }
        }
    }
}

function updateTrapGroupInDB($id = null)
{
    if (! $id) {
        return;
    }
    updateTrapGroup($id);
}

function updateTrapGroup($id = null)
{
    global $form, $pearDB, $oreon;

    if (! $id) {
        return;
    }

    $ret = [];
    $ret = $form->getSubmitValues();

    $rq = 'UPDATE traps_group ';
    $rq .= "SET traps_group_name = '" . $pearDB->escape(htmlentities($ret['name'], ENT_QUOTES, 'UTF-8')) . "' ";
    $rq .= "WHERE traps_group_id = '" . $pearDB->escape($id) . "'";
    $pearDB->query($rq);

    $pearDB->query("DELETE FROM traps_group_relation WHERE traps_group_id = '" . $pearDB->escape($id) . "'");
    if (isset($ret['traps'])) {
        foreach ($ret['traps'] as $trap_id) {
            $query = 'INSERT INTO traps_group_relation (traps_group_id, traps_id) VALUES (' . $pearDB->escape($id)
                . ",'" . $pearDB->escape($trap_id) . "')";
            $pearDB->query($query);
        }
    }

    // Prepare value for changelog
    $fields = CentreonLogAction::prepareChanges($ret);
    $oreon->CentreonLogAction->insertLog('traps_group', $id, $fields['name'], 'c', $fields);
}

function insertTrapGroupInDB($ret = [])
{
    return insertTrapGroup($ret);
}

function insertTrapGroup($ret = [])
{
    global $form, $pearDB, $oreon;

    if (! count($ret)) {
        $ret = $form->getSubmitValues();
    }

    $rq = 'INSERT INTO traps_group ';
    $rq .= '(traps_group_name) ';
    $rq .= 'VALUES ';
    $rq .= "('" . $pearDB->escape(htmlentities($ret['name'], ENT_QUOTES, 'UTF-8')) . "')";
    $dbResult = $pearDB->query($rq);
    $dbResult = $pearDB->query('SELECT MAX(traps_group_id) as max_id FROM traps_group');
    $trap_group_id = $dbResult->fetch();

    $fields = [];
    if (isset($ret['traps'])) {
        $query = 'INSERT INTO traps_group_relation (traps_group_id, traps_id) VALUES (:traps_group_id, :traps_id)';
        $statement = $pearDB->prepare($query);
        foreach ($ret['traps'] as $trap_id) {
            $statement->bindValue(':traps_group_id', $trap_group_id['max_id'], PDO::PARAM_INT);
            $statement->bindValue(':traps_id', (int) $trap_id, PDO::PARAM_INT);
            $statement->execute();
        }
    }

    // Prepare value for changelog
    $fields = CentreonLogAction::prepareChanges($ret);
    $oreon->CentreonLogAction->insertLog('traps_group', $trap_group_id['max_id'], $fields['name'], 'a', $fields);

    return $trap_group_id['max_id'];
}
