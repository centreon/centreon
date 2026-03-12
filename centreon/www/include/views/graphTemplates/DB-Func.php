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
 * Check whether a graph template name is available (no existing template matches).
 *
 * When $name is null, the check targets rows where `name IS NULL`. If
 * $excludeCurrentFormId is true and a global $form is available, the current
 * form's `graph_id` is excluded from the search.
 *
 * @param string|null $name Name to check for availability, or null to check for NULL names.
 * @param bool $excludeCurrentFormId Whether to exclude the current form's graph_id from the check.
 * @return bool `true` if no matching row exists (name is available), `false` otherwise.
 */
function testExistence($name = null, bool $excludeCurrentFormId = true)
{
    global $pearDB, $form;

    $id = null;
    if ($excludeCurrentFormId && isset($form)) {
        $id = $form->getSubmitValue('graph_id');
    }
    $normalizedName = $name !== null ? htmlentities($name, ENT_QUOTES, 'UTF-8') : null;
    $conditions = $normalizedName === null ? 'name IS NULL' : 'name = :name';
    if ($id !== null) {
        $conditions .= ' AND graph_id <> :graphId';
    }

    $statement = $pearDB->prepare(
        'SELECT 1 FROM giv_graphs_template WHERE ' . $conditions . ' LIMIT 1'
    );
    if ($normalizedName !== null) {
        $statement->bindValue(':name', $normalizedName, PDO::PARAM_STR);
    }
    if ($id !== null) {
        $statement->bindValue(':graphId', (int) $id, PDO::PARAM_INT);
    }
    $statement->execute();

    return $statement->fetchColumn() === false;
}

/**
 * Delete graph templates identified by the array's keys from the database.
 *
 * After deletion, ensures a default graph template exists by calling defaultOreonGraph().
 *
 * @param array<int, mixed> $graphs Array whose keys are `graph_id` values to delete; array values are ignored.
 */
function deleteGraphTemplateInDB($graphs = []): void
{
    global $pearDB;

    $stmt = $pearDB->prepare('DELETE FROM giv_graphs_template WHERE graph_id = :graphTemplateId');
    foreach (array_keys($graphs) as $key) {
        $stmt->bindValue(':graphTemplateId', $key, PDO::PARAM_INT);
        $stmt->execute();
    }

    defaultOreonGraph();
}

/**
 * Create copies of the specified graph templates in the database, appending a numeric suffix to each duplicated name.
 *
 * For each provided graph ID this function attempts to create the requested number of duplicates (0–100). Each duplicate:
 * - receives the original template's data with `graph_id` cleared,
 * - has the default flag cleared,
 * - is named by appending `_n` to the original name where `n` is a positive integer chosen to avoid name collisions.
 *
 * Invalid graph IDs or invalid duplication counts are skipped. If the function cannot create the requested number of duplicates for a template (due to name collisions), it logs a warning indicating how many copies were created.
 *
 * @param array<int,mixed> $graphs Associative array where keys are graph template IDs to duplicate.
 * @param array<int,int> $nbrDup Associative array mapping graph template ID keys (same keys as in `$graphs`) to the desired number of duplicates (integer between 0 and 100).
 */
function multipleGraphTemplateInDB($graphs = [], $nbrDup = []): void
{
    global $pearDB;

    if (empty($graphs) || empty($nbrDup)) {
        return;
    }

    $selectStmt = $pearDB->prepare(
        'SELECT * FROM giv_graphs_template WHERE graph_id = :graphTemplateId LIMIT 1'
    );

    foreach (array_keys($graphs) as $key) {
        $graphTemplateId = filter_var($key, FILTER_VALIDATE_INT);
        if ($graphTemplateId === false) {
            continue;
        }

        $dupCount = filter_var($nbrDup[$key] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
        if ($dupCount === false) {
            continue;
        }
        $selectStmt->bindValue(':graphTemplateId', $graphTemplateId, PDO::PARAM_INT);
        $selectStmt->execute();
        $row = $selectStmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            continue;
        }

        unset($row['graph_id']);
        $row['default_tpl1'] = '0';
        $columns = array_keys($row);
        $placeholders = implode(', ', array_map(fn ($col) => ':' . $col, $columns));
        $insertStmt = $pearDB->prepare(
            'INSERT INTO giv_graphs_template (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')'
        );

        $originalName = html_entity_decode((string) $row['name'], ENT_QUOTES, 'UTF-8');
        $suffix = 1;
        for ($i = 0; $i < $dupCount && $suffix <= $dupCount + 1000; $suffix++) {
            $decodedName = $originalName . '_' . $suffix;
            $row['name'] = htmlentities($decodedName, ENT_QUOTES, 'UTF-8');
            if (! testExistence($decodedName, false)) {
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
            error_log("Could only create {$i}/{$dupCount} duplicates for graph template '{$originalName}' ({$key}): suffix search exhausted");
        }
    }
}

/**
 * Ensure a default graph template exists in the database.
 *
 * If no template is currently marked as the default, marks the template with the smallest
 * `graph_id` as the default by setting `default_tpl1` to '1'.
 */
function defaultOreonGraph()
{
    global $pearDB;
    $res = $pearDB->query("SELECT DISTINCT graph_id FROM giv_graphs_template WHERE default_tpl1 = '1' LIMIT 1");
    if ($res->fetch() === false) {
        $pearDB->query(
            "UPDATE giv_graphs_template SET default_tpl1 = '1'"
            . ' WHERE graph_id = (SELECT MIN(graph_id) FROM giv_graphs_template)'
        );
    }
}

/**
 * Clears the default flag for all graph templates by setting `default_tpl1` to '0' in the database.
 */
function noDefaultOreonGraph()
{
    global $pearDB;
    $pearDB->query("UPDATE giv_graphs_template SET default_tpl1 = '0'");
}

/**
 * @param $graph_id
 */
function updateGraphTemplateInDB($graph_id = null): void
{
    if (! $graph_id) {
        return;
    }
    updateGraphTemplate((int) $graph_id);
}

function insertGraphTemplateInDB()
{
    return insertGraphTemplate();
}

/**
 * Insert a new graph template using values submitted via the form.
 *
 * If the submitted data sets the template as default, existing defaults are cleared before insertion.
 * After a successful insert, ensures a default template exists.
 *
 * @return int The new graph template's ID on success, or 0 on failure.
 */
function insertGraphTemplate(): int
{
    global $form, $pearDB;

    $ret = $form->getSubmitValues();
    if (isset($ret['default_tpl1']) && ((int) $ret['default_tpl1']) === 1) { // === 1 means that the checkbox is checked
        noDefaultOreonGraph();
    }
    $rq = <<<'SQL'
        INSERT INTO `giv_graphs_template` (
            `name`, `vertical_label`, `width`,
            `height`, `base`, `lower_limit`,
            `upper_limit`, `size_to_max`, `default_tpl1`,
            `scaled`, `stacked` , `comment`,
            `split_component`
        ) VALUES (
            :name, :vertical_label, :width, :height, :base, :lower_limit, :upper_limit, :size_to_max, :default_tpl1, 
            :scaled, :stacked, :comment, null
        )
        SQL;

    $bindValues = getBindValues($ret);

    $stmt = $pearDB->prepare($rq);
    foreach ($bindValues as $key => [$type, $value]) {
        $stmt->bindValue($key, $value, $type);
    }
    $stmt->execute();
    $graphId = (int) $pearDB->lastInsertId();
    if ($graphId <= 0) {
        return 0;
    }
    defaultOreonGraph();

    return $graphId;
}

/**
 * @param int|null $graph_id
 */
function updateGraphTemplate(?int $graph_id = null): void
{
    global $form, $pearDB;

    if (! $graph_id) {
        return;
    }
    $ret = $form->getSubmitValues();
    if (isset($ret['default_tpl1']) && ((int) $ret['default_tpl1']) === 1) { // === 1 means that the checkbox is checked
        noDefaultOreonGraph();
    }
    $rq = <<<'SQL'
        UPDATE giv_graphs_template
        SET name = :name,
            vertical_label = :vertical_label,
            width = :width,
            height = :height,
            base = :base,
            lower_limit = :lower_limit,
            upper_limit = :upper_limit,
            size_to_max = :size_to_max,
            default_tpl1 = :default_tpl1,
            split_component = null,
            scaled = :scaled,
            stacked = :stacked,
            comment = :comment
        WHERE graph_id = :graph_id
        SQL;

    $bindValues = getBindValues($ret);
    $bindValues[':graph_id'] = [PDO::PARAM_INT, $graph_id];

    $stmt = $pearDB->prepare($rq);
    foreach ($bindValues as $key => [$type, $value]) {
        $stmt->bindValue($key, $value, $type);
    }

    $stmt->execute();
    defaultOreonGraph();
}

/**
 * Build an associative map of PDO bind parameters for a graph template record.
 *
 * @param array{
 *     name: string,
 *     vertical_label: string,
 *     width: int,
 *     height: int,
 *     base: int,
 *     lower_limit: int,
 *     upper_limit: int,
 *     size_to_max: int,
 *     default_tpl1: int,
 *     stacked: int,
 *     scaled: int,
 *     comment: string
 * } $data Input values (typically from a form) for graph template fields.
 *
 * @return array<string, array{int,mixed}> Associative array keyed by PDO parameter names (e.g. ':name'); each value is a two-element array [PDO::PARAM_* constant, bound value|null].
 */
function getBindValues(array $data): array
{
    return [
        ':name' => isset($data['name']) && $data['name'] !== ''
            ? [PDO::PARAM_STR, htmlentities($data['name'], ENT_QUOTES, 'UTF-8')]
            : [PDO::PARAM_NULL, null],
        ':vertical_label' => isset($data['vertical_label']) && $data['vertical_label'] !== ''
            ? [PDO::PARAM_STR, htmlentities($data['vertical_label'], ENT_QUOTES, 'UTF-8')]
            : [PDO::PARAM_NULL, null],
        ':width' => isset($data['width']) && $data['width'] !== ''
            ? [PDO::PARAM_INT, $data['width']]
            : [PDO::PARAM_NULL, null],
        ':height' => isset($data['height']) && $data['height'] !== ''
            ? [PDO::PARAM_INT, $data['height']]
            : [PDO::PARAM_NULL, null],
        ':base' => isset($data['base']) && $data['base'] !== ''
            ? [PDO::PARAM_INT, $data['base']]
            : [PDO::PARAM_NULL, null],
        ':lower_limit' => isset($data['lower_limit']) && $data['lower_limit'] !== ''
            ? [PDO::PARAM_INT, $data['lower_limit']]
            : [PDO::PARAM_NULL, null],
        ':upper_limit' => isset($data['upper_limit']) && $data['upper_limit'] !== ''
            ? [PDO::PARAM_INT, $data['upper_limit']]
            : [PDO::PARAM_NULL, null],
        ':size_to_max' => isset($data['size_to_max']) && $data['size_to_max'] !== ''
            ? [PDO::PARAM_INT, $data['size_to_max']]
            : [PDO::PARAM_INT, 0],
        // default_tpl1, stacked, scaled are enum('0','1') columns.
        // PARAM_STR stringifies for the enum.
        ':default_tpl1' => isset($data['default_tpl1']) && in_array($data['default_tpl1'], ['0', '1'], true)
            ? [PDO::PARAM_STR, $data['default_tpl1']]
            : [PDO::PARAM_STR, '0'],
        ':stacked' => isset($data['stacked']) && in_array($data['stacked'], ['0', '1'], true)
            ? [PDO::PARAM_STR, $data['stacked']]
            : [PDO::PARAM_NULL, null],
        ':scaled' => isset($data['scaled']) && in_array($data['scaled'], ['0', '1'], true)
            ? [PDO::PARAM_STR, $data['scaled']]
            : [PDO::PARAM_STR, '0'],
        ':comment' => isset($data['comment']) && $data['comment'] !== ''
            ? [PDO::PARAM_STR, htmlentities($data['comment'], ENT_QUOTES, 'UTF-8')]
            : [PDO::PARAM_NULL, null],
    ];
}
