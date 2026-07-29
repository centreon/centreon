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

require_once realpath(__DIR__ . '/../../../../../../config/centreon.config.php');

require_once __DIR__ . '/argumentsXmlFunction.php';

require_once _CENTREON_PATH_ . '/www/class/centreonDB.class.php';

// Get session
require_once _CENTREON_PATH_ . 'www/class/centreonSession.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreon.class.php';

if (! isset($_SESSION['centreon'])) {
    CentreonSession::start(1);
}

if (isset($_SESSION['centreon'])) {
    $oreon = $_SESSION['centreon'];
} else {
    exit;
}

// Get language
$locale = $oreon->user->get_lang();
putenv("LANG={$locale}");
setlocale(LC_ALL, $locale);
bindtextdomain('messages', _CENTREON_PATH_ . 'www/locale/');
bind_textdomain_codeset('messages', 'UTF-8');
textdomain('messages');

// start init db
$db = new CentreonDB();

$args = [];

if (isset($_GET['cmdId'], $_GET['svcId'], $_GET['svcTplId'], $_GET['o'])) {
    $cmdId = CentreonDB::escape($_GET['cmdId']);
    $svcId = CentreonDB::escape($_GET['svcId']);
    $svcTplId = CentreonDB::escape($_GET['svcTplId']);
    $o = CentreonDB::escape($_GET['o']);

    $tab = [];
    if (! $cmdId && $svcTplId) {
        while (1) {
            $query4 = "SELECT service_template_model_stm_id, command_command_id, command_command_id_arg
                            FROM `service`
                            WHERE service_id = '" . $svcTplId . "'";
            $res4 = $db->query($query4);
            $row4 = $res4->fetchRow();
            if (isset($row4['command_command_id']) && $row4['command_command_id']) {
                $cmdId = $row4['command_command_id'];
                break;
            }
            if (! isset($row4['service_template_model_stm_id']) || ! $row4['service_template_model_stm_id']) {
                break;
            }
            if (isset($tab[$row4['service_template_model_stm_id']])) {
                break;
            }
            $svcTplId = $row4['service_template_model_stm_id'];
            $tab[$svcTplId] = 1;
        }
    }

    $argTab = [];
    $exampleTab = [];
    $valueTab = [];

    $query2 = 'SELECT command_line, command_example FROM command WHERE command_id = :cmd_id LIMIT 1';
    $statement = $db->prepare($query2);
    $statement->bindValue(':cmd_id', $cmdId, PDO::PARAM_INT);
    $statement->execute();
    if ($row2 = $statement->fetch()) {
        $cmdLine = $row2['command_line'];
        preg_match_all('/\\$(ARG[0-9]+)\\$/', $cmdLine, $matches);
        foreach ($matches[1] as $key => $value) {
            $argTab[$value] = $value;
        }
        $exampleTab = preg_split('/\!/', $row2['command_example']);
        if (is_array($exampleTab)) {
            foreach ($exampleTab as $key => $value) {
                $nbTmp = $key;
                $exampleTab['ARG' . $nbTmp] = $value;
                unset($exampleTab[$key]);
            }
        }
    }

    $cmdStatement = $db->prepare('SELECT command_command_id_arg '
        . 'FROM service '
        . 'WHERE service_id = :svcId LIMIT 1');
    $cmdStatement->bindValue(':svcId', (int) $svcId, PDO::PARAM_INT);
    $cmdStatement->execute();
    if ($cmdStatement->rowCount()) {
        $row3 = $cmdStatement->fetchRow();
        $valueTab = preg_split('/(?<!\\\)\!/', $row3['command_command_id_arg']);
        if (is_array($valueTab)) {
            foreach ($valueTab as $key => $value) {
                $nbTmp = $key;
                $valueTab['ARG' . $nbTmp] = $value;
                unset($valueTab[$key]);
            }
        } else {
            $exampleTab = [];
        }
    }

    $macroStatement = $db->prepare('SELECT macro_name, macro_description '
        . 'FROM command_arg_description '
        . 'WHERE cmd_id = :cmdId ORDER BY macro_name');
    $macroStatement->bindValue(':cmdId', (int) $cmdId, PDO::PARAM_INT);
    $macroStatement->execute();
    while ($row = $macroStatement->fetchRow()) {
        $argTab[$row['macro_name']] = $row['macro_description'];
    }
    $macroStatement->closeCursor();

    $disabled = $o === 'w';
    foreach ($argTab as $name => $description) {
        $args[] = [
            'name' => $name,
            'description' => $description,
            'value' => $valueTab[$name] ?? '',
            'example' => isset($exampleTab[$name]) ? myDecodeValue($exampleTab[$name]) : '',
            'disabled' => $disabled,
        ];
    }
}

header('Content-Type: application/json');
header('Pragma: no-cache');
header('Expires: 0');
header('Cache-Control: no-cache, must-revalidate');
echo json_encode(['args' => $args]);
