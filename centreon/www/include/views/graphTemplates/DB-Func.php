<?php

/*
* Copyright 2005-2020 Centreon
* Centreon is developed by : Julien Mathis and Romain Le Merlus under
* GPL Licence 2.0.
*
* This program is free software; you can redistribute it and/or modify it under
* the terms of the GNU General Public License as published by the Free Software
* Foundation ; either version 2 of the License.
*
* This program is distributed in the hope that it will be useful, but WITHOUT ANY
* WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
* PARTICULAR PURPOSE. See the GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License along with
* this program; if not, see <http://www.gnu.org/licenses>.
*
* Linking this program statically or dynamically with other modules is making a
* combined work based on this program. Thus, the terms and conditions of the GNU
* General Public License cover the whole combination.
*
* As a special exception, the copyright holders of this program give Centreon
* permission to link this program with independent modules to produce an executable,
* regardless of the license terms of these independent modules, and to copy and
* distribute the resulting executable under terms of Centreon choice, provided that
* Centreon also meet, for each linked independent module, the terms  and conditions
* of the license of that module. An independent module is a module which is not
* derived from this program. If you modify this program, you may extend this
* exception to your version of the program, but you are not obliged to do so. If you
* do not wish to do so, delete this exception statement from your version.
*
* For more information : contact@centreon.com
*
*/

if (!isset($centreon)) {
    exit();
}

function testExistence($name = null)
{
    global $pearDB, $form;

    $id = null;
    if (isset($form)) {
        $id = $form->getSubmitValue('graph_id');
    }
    $query = "SELECT graph_id, name FROM giv_graphs_template WHERE name = '" .
        htmlentities($name, ENT_QUOTES, "UTF-8") . "'";
    $res = $pearDB->query($query);
    $graph = $res->fetch();
    // Modif case
    if ($res->rowCount() >= 1 && $graph["graph_id"] == $id) {
        return true;
    } elseif ($res->rowCount() >= 1 && $graph["graph_id"] != $id) {
        // duplicate entry
        return false;
    } else {
        return true;
    }
}

/**
 * Deletes from the DB the graph templates provided
 *
 * @param  int[] $graphs
 * @return void
 */
function deleteGraphTemplateInDB($graphs = []): void
{
    global $pearDB;

    foreach ($graphs as $key => $value) {
        $stmt = $pearDB->prepare('DELETE FROM giv_graphs_template WHERE graph_id = :graphTemplateId');
        $stmt->bindValue(':graphTemplateId', $key, \PDO::PARAM_INT);
        $stmt->execute();
    }

    defaultOreonGraph();
}

/*
 * Duplicates the selected graph templates in the DB
 * by adding _n to the duplicated graph template name
 *
 * @param  int[] $graphs
 * @param  int[] $nbrDup
 * @return void
 */
function multipleGraphTemplateInDB($graphs = [], $nbrDup = []): void
{
    global $pearDB;
    if (!empty($graphs) && !empty($nbrDup)) {
        foreach ($graphs as $key => $value) {
            $stmt = $pearDB->prepare('SELECT * FROM giv_graphs_template WHERE graph_id = :graphTemplateId LIMIT 1');
            $stmt->bindValue(':graphTemplateId', $key, \PDO::PARAM_INT);
            $stmt->execute();
            if ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $row["graph_id"] = '';
                $row["default_tpl1"] = '0';
                for ($i = 1; $i <= $nbrDup[$key]; $i++) {
                    $val = null;
                    foreach ($row as $key2 => $value2) {
                        $value2 = is_int($value2) ? (string) $value2 : $value2;
                        $key2 == "name" ? ($name = $value2 = $value2 . "_" . $i) : null;
                        $val
                            ? $val .= ($value2 != null ? (", '" . $value2 . "'") : ", NULL")
                            : $val .= ($value2 != null ? ("'" . $value2 . "'") : "NULL");
                    }
                    if (testExistence($name)) {
                        $val ? $rq = "INSERT INTO giv_graphs_template VALUES (" . $val . ")" : $rq = null;
                        $pearDB->query($rq);
                    }
                }
            }
        }
    }
}

function defaultOreonGraph()
{
    global $pearDB;
    $rq = "SELECT DISTINCT graph_id FROM giv_graphs_template WHERE default_tpl1 = '1'";
    $res = $pearDB->query($rq);
    if (!$res->rowCount()) {
        $rq = "UPDATE giv_graphs_template SET default_tpl1 = '1' " .
            "WHERE graph_id = (SELECT MIN(graph_id) FROM giv_graphs_template)";
        $pearDB->query($rq);
    }
}

function noDefaultOreonGraph()
{
    global $pearDB;
    $rq = "UPDATE giv_graphs_template SET default_tpl1 = '0'";
    $pearDB->query($rq);
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
    $graph_id = insertGraphTemplate();
    return ($graph_id);
}

function insertGraphTemplate(): int
{
    global $form, $pearDB;

    $ret = $form->getSubmitValues();
    if (isset($ret["default_tpl1"]) && ((int) $ret["default_tpl1"]) === 1) { // === 1 means that the checkbox is checked
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
function updateGraphTemplate(int $graph_id = null): void
{
    global $form, $pearDB;

    if (! $graph_id) {
        return;
    }
    $ret = $form->getSubmitValues();
    if (isset($ret["default_tpl1"]) && ((int) $ret["default_tpl1"]) === 1) { // === 1 means that the checkbox is checked
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
    $bindValues[':graph_id'] = [\PDO::PARAM_INT, $graph_id];

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
    $bindValues = [];
    $bindValues[':name'] = isset($data['name']) && $data['name'] !== ''
        ? [\PDO::PARAM_STR, htmlentities($data['name'], ENT_QUOTES, 'UTF-8')]
        : [\PDO::PARAM_NULL, null];

    $bindValues[':vertical_label'] = isset($data['vertical_label']) && $data['vertical_label'] !== ''
        ? [\PDO::PARAM_STR, htmlentities($data['vertical_label'], ENT_QUOTES, 'UTF-8')]
        : [\PDO::PARAM_NULL, null];

    $bindValues[':width'] = isset($data['width']) && $data['width'] !== ''
        ? [\PDO::PARAM_INT, $data["width"]]
        : [\PDO::PARAM_NULL, null];

    $bindValues[':height'] = isset($data['height']) && $data['height'] !== ''
        ? [\PDO::PARAM_INT, $data["height"]]
        : [\PDO::PARAM_NULL, null];

    $bindValues[':base'] = isset($data['base']) && $data['base'] !== ''
        ? [\PDO::PARAM_INT, $data["base"]]
        : [\PDO::PARAM_NULL, null];

    $bindValues[':lower_limit'] = isset($data['lower_limit']) && $data['lower_limit'] !== ''
        ? [\PDO::PARAM_INT, $data["lower_limit"]]
        : [\PDO::PARAM_NULL, null];

    $bindValues[':upper_limit'] = isset($data['upper_limit']) && $data['upper_limit'] !== ''
        ? [\PDO::PARAM_INT, $data["upper_limit"]]
        : [\PDO::PARAM_NULL, null];

    $bindValues[':size_to_max'] = isset($data['size_to_max']) && $data['size_to_max'] !== ''
        ? [\PDO::PARAM_INT, $data["size_to_max"]]
        : [\PDO::PARAM_INT, 0];

    $bindValues[':default_tpl1'] = isset($data['default_tpl1']) && $data['default_tpl1'] !== ''
        ? [\PDO::PARAM_STR, (int) $data["default_tpl1"]]
        : [\PDO::PARAM_STR, 0];

    $bindValues[':stacked'] = isset($data['stacked']) && $data['stacked'] !== ''
        ? [\PDO::PARAM_STR, (int) $data["stacked"]]
        : [\PDO::PARAM_NULL, null];

    $bindValues[':scaled'] = isset($data['scaled']) && $data['scaled'] !== ''
        ? [\PDO::PARAM_STR, (int) $data["scaled"]]
        : [\PDO::PARAM_STR, 0];

    $bindValues[':comment'] = isset($data['comment']) && $data['comment'] !== ''
        ? [\PDO::PARAM_STR, htmlentities($data['comment'], ENT_QUOTES, 'UTF-8')]
        : [\PDO::PARAM_NULL, null];

    return $bindValues;
}
