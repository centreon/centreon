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

function testExistence($name = null)
{
    global $pearDB, $form;

    $id = null;
    if (isset($form)) {
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
 * Deletes from the DB the graph templates provided
 *
 * @param array<int, mixed> $graphs graph IDs as keys
 * @return void
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

/*
 * Duplicates the selected graph templates in the DB
 * by adding _n to the duplicated graph template name
 *
 * @param  array<int, mixed> $graphs graph IDs as keys
 * @param  array<int, int> $nbrDup
 * @return void
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
        $dupCount = (int) ($nbrDup[$key] ?? 0);
        if ($dupCount < 1) {
            continue;
        }
        $selectStmt->bindValue(':graphTemplateId', (int) $key, PDO::PARAM_INT);
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
            if (! testExistence($decodedName)) {
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

function defaultOreonGraph()
{
    global $pearDB;
    $res = $pearDB->query("SELECT DISTINCT graph_id FROM giv_graphs_template WHERE default_tpl1 = '1'");
    if (! $res->rowCount()) {
        $pearDB->query(
            "UPDATE giv_graphs_template SET default_tpl1 = '1'"
            . ' WHERE graph_id = (SELECT MIN(graph_id) FROM giv_graphs_template)'
        );
    }
}

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
    $graphId = $pearDB->lastInsertId();
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
 * } $data
 *
 * @return array{string, array{int, mixed}
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
        // The (int) cast sanitizes to 0/1; PARAM_STR stringifies for the enum.
        ':default_tpl1' => isset($data['default_tpl1']) && $data['default_tpl1'] !== ''
            ? [PDO::PARAM_STR, (int) $data['default_tpl1']]
            : [PDO::PARAM_STR, 0],
        ':stacked' => isset($data['stacked']) && $data['stacked'] !== ''
            ? [PDO::PARAM_STR, (int) $data['stacked']]
            : [PDO::PARAM_NULL, null],
        ':scaled' => isset($data['scaled']) && $data['scaled'] !== ''
            ? [PDO::PARAM_STR, (int) $data['scaled']]
            : [PDO::PARAM_STR, 0],
        ':comment' => isset($data['comment']) && $data['comment'] !== ''
            ? [PDO::PARAM_STR, htmlentities($data['comment'], ENT_QUOTES, 'UTF-8')]
            : [PDO::PARAM_NULL, null],
    ];
}
