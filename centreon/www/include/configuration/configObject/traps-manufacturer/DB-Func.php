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
    $statement = $pearDB->prepare('SELECT name, id FROM traps_vendor WHERE name = :name');
    $statement->bindValue(':name', $name, PDO::PARAM_STR);
    $statement->execute();
    $mnftr = $statement->fetch();
    if ($mnftr === false) {
        return true;
    }

    return $mnftr['id'] == $id;
}

function deleteMnftrInDB($mnftr = [])
{
    global $pearDB, $oreon;
    $selectStatement = $pearDB->prepare('SELECT name FROM `traps_vendor` WHERE `id` = :id LIMIT 1');
    $deleteStatement = $pearDB->prepare('DELETE FROM traps_vendor WHERE id = :id');
    foreach (array_keys($mnftr) as $key) {
        $selectStatement->bindValue(':id', (int) $key, PDO::PARAM_INT);
        $selectStatement->execute();
        $row = $selectStatement->fetch();

        $deleteStatement->bindValue(':id', (int) $key, PDO::PARAM_INT);
        $deleteStatement->execute();
        $oreon->CentreonLogAction->insertLog(
            'manufacturer',
            $key,
            $row !== false ? $row['name'] : "id:{$key}",
            'd'
        );
    }
}

function multipleMnftrInDB($mnftr = [], $nbrDup = [])
{
    global $pearDB, $oreon;

    $selectStmt = $pearDB->prepare(
        'SELECT * FROM traps_vendor WHERE id = :id LIMIT 1'
    );

    foreach (array_keys($mnftr) as $key) {
        $selectStmt->bindValue(':id', (int) $key, PDO::PARAM_INT);
        $selectStmt->execute();
        $row = $selectStmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            continue;
        }
        unset($row['id']);
        $columns = array_keys($row);
        $placeholders = implode(', ', array_map(fn ($col) => ':' . $col, $columns));
        $insertStmt = $pearDB->prepare(
            'INSERT INTO traps_vendor (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')'
        );
        $dupCount = (int) ($nbrDup[$key] ?? 0);
        $originalName = $row['name'];
        $suffix = 1;
        for ($i = 0; $i < $dupCount && $suffix <= $dupCount + 1000; $suffix++) {
            $name = $originalName . '_' . $suffix;
            if (! testMnftrExistence($name)) {
                continue;
            }
            $i++;
            $row['name'] = $name;
            $fields = $row;
            foreach ($columns as $col) {
                $value = $row[$col];
                $insertStmt->bindValue(':' . $col, $value, $value === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            }
            $insertStmt->execute();
            $newMnftrId = (int) $pearDB->lastInsertId();
            $oreon->CentreonLogAction->insertLog(
                'manufacturer',
                $newMnftrId,
                $name,
                'a',
                $fields
            );
        }
        if ($i < $dupCount) {
            error_log("Could only create {$i}/{$dupCount} duplicates for manufacturer '{$originalName}' ({$key}): suffix search exhausted");
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

    $ret = $form->getSubmitValues();
    $statement = $pearDB->prepare(
        'UPDATE traps_vendor SET name = :name, alias = :alias, description = :description WHERE id = :id'
    );
    $statement->bindValue(':name', $ret['name'], PDO::PARAM_STR);
    $statement->bindValue(':alias', $ret['alias'], PDO::PARAM_STR);
    $statement->bindValue(':description', $ret['description'], PDO::PARAM_STR);
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
    $statement->bindValue(':name', $ret['name'], PDO::PARAM_STR);
    $statement->bindValue(':alias', $ret['alias'], PDO::PARAM_STR);
    $statement->bindValue(':description', $ret['description'], PDO::PARAM_STR);
    $statement->execute();
    $mnftrId = (int) $pearDB->lastInsertId();

    // Prepare value for changelog
    $fields = CentreonLogAction::prepareChanges($ret);
    $oreon->CentreonLogAction->insertLog('manufacturer', $mnftrId, $fields['name'], 'a', $fields);

    return $mnftrId;
}
