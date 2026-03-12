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

function testMnftrExistence($name = null, bool $excludeCurrentFormId = true)
{
    global $pearDB;
    global $form;
    $id = null;
    if ($excludeCurrentFormId && isset($form)) {
        $id = $form->getSubmitValue('id');
    }
    $query = 'SELECT 1 FROM traps_vendor WHERE name = :name';
    if ($id !== null) {
        $query .= ' AND id <> :vendorId';
    }
    $statement = $pearDB->prepare($query . ' LIMIT 1');
    $statement->bindValue(':name', $name, PDO::PARAM_STR);
    if ($id !== null) {
        $statement->bindValue(':vendorId', (int) $id, PDO::PARAM_INT);
    }
    $statement->execute();

    return $statement->fetchColumn() === false;
}

function deleteMnftrInDB($mnftr = [])
{
    global $pearDB, $oreon;
    $selectStatement = $pearDB->prepare('SELECT name FROM `traps_vendor` WHERE `id` = :id LIMIT 1');
    $deleteStatement = $pearDB->prepare('DELETE FROM traps_vendor WHERE id = :id');
    foreach (array_keys($mnftr) as $key) {
        $id = filter_var($key, FILTER_VALIDATE_INT);
        if ($id === false) {
            continue;
        }

        $selectStatement->bindValue(':id', $id, PDO::PARAM_INT);
        $selectStatement->execute();
        $row = $selectStatement->fetch();
        if ($row === false) {
            continue;
        }

        $deleteStatement->bindValue(':id', $id, PDO::PARAM_INT);
        $deleteStatement->execute();
        $oreon->CentreonLogAction->insertLog(
            'manufacturer',
            $id,
            $row['name'],
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
        $id = filter_var($key, FILTER_VALIDATE_INT);
        if ($id === false) {
            continue;
        }

        $selectStmt->bindValue(':id', $id, PDO::PARAM_INT);
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
        $dupCount = filter_var($nbrDup[$key] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
        if ($dupCount === false) {
            continue;
        }
        $originalName = $row['name'];
        $suffix = 1;
        for ($i = 0; $i < $dupCount && $suffix <= $dupCount + 1000; $suffix++) {
            $name = $originalName . '_' . $suffix;
            if (! testMnftrExistence($name, false)) {
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
    if ($mnftrId <= 0) {
        throw new RuntimeException('Failed to retrieve inserted manufacturer id');
    }

    // Prepare value for changelog
    $fields = CentreonLogAction::prepareChanges($ret);
    $oreon->CentreonLogAction->insertLog('manufacturer', $mnftrId, $fields['name'], 'a', $fields);

    return $mnftrId;
}
