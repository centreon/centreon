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
    $encodedName = htmlentities($name ?? '', ENT_QUOTES, 'UTF-8');
    $query = 'SELECT name, id FROM traps_vendor WHERE name = :name';
    $statement = $pearDB->prepare($query);
    $statement->bindValue(':name', $encodedName, PDO::PARAM_STR);
    $statement->execute();
    $mnftr = $statement->fetch();
    // Modif case
    if ($statement->rowCount() >= 1 && $mnftr['id'] == $id) {
        return true;
    } // Duplicate entry

    return ! ($statement->rowCount() >= 1 && $mnftr['id'] != $id);
}

function deleteMnftrInDB($mnftr = [])
{
    global $pearDB, $oreon;
    foreach ($mnftr as $key => $value) {
        $stmt = $pearDB->prepare('SELECT name FROM `traps_vendor` WHERE `id` = :id LIMIT 1');
        $stmt->bindValue(':id', $key, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        $stmt = $pearDB->prepare('DELETE FROM traps_vendor WHERE id = :id');
        $stmt->bindValue(':id', (int) $key, PDO::PARAM_INT);
        $stmt->execute();
        $oreon->CentreonLogAction->insertLog('manufacturer', $key, $row['name'], 'd');
    }
}

function multipleMnftrInDB($mnftr = [], $nbrDup = [])
{
    foreach ($mnftr as $key => $value) {
        global $pearDB, $oreon;
        $stmt = $pearDB->prepare('SELECT * FROM traps_vendor WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', (int) $key, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        unset($row['id']);
        $columns = array_keys($row);
        $placeholders = implode(', ', array_map(fn ($col) => ':' . $col, $columns));
        $insertStmt = $pearDB->prepare(
            'INSERT INTO traps_vendor (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')'
        );

        $originalName = $row['name'];
        for ($i = 1; $i <= $nbrDup[$key]; $i++) {
            $name = $originalName . '_' . $i;
            $row['name'] = $name;

            if (testMnftrExistence($name)) {
                foreach ($columns as $col) {
                    $insertStmt->bindValue(':' . $col, $row[$col], $row[$col] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                }
                $insertStmt->execute();

                $fields = $row;
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
    $statement = $pearDB->prepare(
        'UPDATE traps_vendor SET name = :name, alias = :alias, description = :description WHERE id = :id'
    );
    $statement->bindValue(':name', htmlentities($ret['name'], ENT_QUOTES, 'UTF-8'), PDO::PARAM_STR);
    $statement->bindValue(':alias', htmlentities($ret['alias'], ENT_QUOTES, 'UTF-8'), PDO::PARAM_STR);
    $statement->bindValue(':description', htmlentities($ret['description'], ENT_QUOTES, 'UTF-8'), PDO::PARAM_STR);
    $statement->bindValue(':id', (int) $id, PDO::PARAM_INT);
    $statement->execute();

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

    $statement = $pearDB->prepare(
        'INSERT INTO traps_vendor (name, alias, description) VALUES (:name, :alias, :description)'
    );
    $statement->bindValue(':name', htmlentities($ret['name'], ENT_QUOTES, 'UTF-8'), PDO::PARAM_STR);
    $statement->bindValue(':alias', htmlentities($ret['alias'], ENT_QUOTES, 'UTF-8'), PDO::PARAM_STR);
    $statement->bindValue(':description', htmlentities($ret['description'], ENT_QUOTES, 'UTF-8'), PDO::PARAM_STR);
    $statement->execute();
    $dbResult = $pearDB->query('SELECT MAX(id) FROM traps_vendor');
    $mnftr_id = $dbResult->fetch();

    // Prepare value for changelog
    $fields = CentreonLogAction::prepareChanges($ret);
    $oreon->CentreonLogAction->insertLog('manufacturer', $mnftr_id['MAX(id)'], $fields['name'], 'a', $fields);

    return $mnftr_id['MAX(id)'];
}
