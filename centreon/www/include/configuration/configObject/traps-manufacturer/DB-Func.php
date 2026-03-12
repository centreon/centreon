<?php

/**
 * Determine whether a manufacturer name is available (no existing row with the same name).
 *
 * @param string|null $name The manufacturer name to check.
 * @param bool $excludeCurrentFormId If true and a global form is present, exclude the form's current `id` from the existence check.
 * @return bool `true` if no matching row exists (name is available), `false` otherwise.
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

/**
 * Delete manufacturers whose IDs are provided as keys in the given array and log each deletion.
 *
 * For each key in `$mnftr`, the key is validated as an integer ID; invalid keys are skipped.
 * If a manufacturer with the ID exists, it is removed from `traps_vendor` and a deletion action is recorded.
 *
 * @param array $mnftr An array whose keys are manufacturer IDs to delete (values are ignored).
 */
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

/**
 * Create multiple duplicated manufacturer records based on existing manufacturers.
 *
 * For each manufacturer id provided as a key in `$mnftr`, attempts to create up to the
 * corresponding duplicate count from `$nbrDup` by appending a numeric suffix to the
 * original name. Invalid or non-existent ids are skipped. Names that already exist are
 * skipped. Each successful insertion is logged; if the requested number of duplicates
 * cannot be created due to name collisions or suffix exhaustion, an error is emitted.
 *
 * @param array $mnftr Array whose keys are manufacturer ids to duplicate.
 * @param array $nbrDup Array mapping the same keys to the desired number of duplicates (integer, 0–100).
 */
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

/**
 * Update a manufacturer record with submitted form values and record the change in the audit log.
 *
 * Uses the current form submission values for `name`, `alias`, and `description`. If `$id` is falsy the
 * function returns without making changes. The performed update is recorded via the Centreon audit log.
 *
 * @param int|null $id The manufacturer id to update; if null or falsy no action is taken.
 */
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

/**
 * Insert a new manufacturer using provided data or current form submission and log its creation.
 *
 * If `$ret` is empty, submission values are retrieved from the global form object.
 *
 * @param array $ret Associative array with keys `name`, `alias`, and `description` used for the new manufacturer.
 * @return int The ID of the newly created manufacturer.
 */
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
