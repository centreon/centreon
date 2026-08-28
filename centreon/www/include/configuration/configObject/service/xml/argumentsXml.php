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

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ValueObject\QueryParameter;

require_once realpath(__DIR__ . '/../../../../../../config/centreon.config.php');

require_once __DIR__ . '/argumentsXmlFunction.php';

require_once _CENTREON_PATH_ . '/www/class/centreonDB.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonXML.class.php';

// Get session
require_once _CENTREON_PATH_ . 'www/class/centreonSession.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreon.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonACL.class.php';

if (! isset($_SESSION['centreon'])) {
    CentreonSession::start(1);
}

if (isset($_SESSION['centreon'])) {
    $oreon = $_SESSION['centreon'];
} else {
    exit;
}

// The endpoint hands back persisted command arguments for the ids it is given,
// so reaching one of the three pages that embed the form is the minimum. The
// form is included by formService.php (60201 services by host, 60202 by host
// group) and by formServiceTemplateModel.php (60206).
if (! $oreon->user->admin) {
    $access = $oreon->user->access;
    $reachesForm = $access !== null
        && ($access->page(60201) !== 0 || $access->page(60202) !== 0 || $access->page(60206) !== 0);

    if (! $reachesForm) {
        http_response_code(403);

        exit;
    }
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
$xml = new CentreonXML();

$xml->startElement('root');
$xml->startElement('main');
$xml->writeElement('argLabel', _('Argument'));
$xml->writeElement('argValue', _('Value'));
$xml->writeElement('argExample', _('Example'));
$xml->writeElement('noArgLabel', _('No argument found for this command'));
$xml->endElement();

if (isset($_GET['cmdId'], $_GET['svcId'], $_GET['svcTplId'], $_GET['o'])) {
    $cmdId = CentreonDB::escape($_GET['cmdId']);
    $svcId = CentreonDB::escape($_GET['svcId']);
    $svcTplId = CentreonDB::escape($_GET['svcTplId']);
    $o = CentreonDB::escape($_GET['o']);

    // Page access alone would let any id be probed, so the service is also
    // checked against the caller ACL. Templates are skipped deliberately:
    // service_register = '0' rows carry no centreon_acl entry of their own,
    // and the template chain is only reachable through the form the page
    // check above already gates.
    if ((int) $svcId !== 0 && ! $oreon->user->admin) {
        $isMonitoredService = (bool) $db->fetchOne(
            <<<'SQL'
                SELECT 1 FROM `service`
                WHERE service_id = :svcId AND service_register = '1'
                LIMIT 1
                SQL,
            QueryParameters::create([QueryParameter::int('svcId', (int) $svcId)])
        );

        if ($isMonitoredService) {
            $groupIds = array_values(array_filter(array_map(
                'intval',
                array_keys($oreon->user->access->getAccessGroups())
            )));

            $isGranted = false;
            if ($groupIds !== []) {
                $aclDbName       = $db->getConnectionConfig()->getDatabaseNameRealTime();
                $aclPlaceholders = [];
                $aclParameters   = [QueryParameter::int('svcId', (int) $svcId)];
                foreach ($groupIds as $index => $groupId) {
                    $placeholder       = 'acl_gid' . $index;
                    $aclPlaceholders[] = ':' . $placeholder;
                    $aclParameters[]   = QueryParameter::int($placeholder, $groupId);
                }
                $aclIn = implode(', ', $aclPlaceholders);

                $isGranted = (bool) $db->fetchOne(
                    <<<SQL
                        SELECT 1 FROM `{$aclDbName}`.centreon_acl
                        WHERE service_id = :svcId AND group_id IN ({$aclIn})
                        LIMIT 1
                        SQL,
                    QueryParameters::create($aclParameters)
                );
            }

            if (! $isGranted) {
                http_response_code(403);

                exit;
            }
        }
    }

    $tab = [];
    if (! $cmdId && $svcTplId) {
        while (1) {
            $stmt4 = $db->prepare(
                'SELECT service_template_model_stm_id, command_command_id, command_command_id_arg
                FROM `service`
                WHERE service_id = :svcTplId'
            );
            $stmt4->bindValue(':svcTplId', (int) $svcTplId, PDO::PARAM_INT);
            $stmt4->execute();
            $row4 = $stmt4->fetch(PDO::FETCH_ASSOC);
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

    // Write XML
    $style = 'list_two';
    $disabled = 0;
    $nbArg = 0;
    foreach ($argTab as $name => $description) {
        $style = $style == 'list_one' ? 'list_two' : 'list_one';
        if ($o == 'w') {
            $disabled = 1;
        }
        $xml->startElement('arg');
        $xml->writeElement('name', $name, false);
        $xml->writeElement('description', $description, false);
        $xml->writeElement('value', $valueTab[$name] ?? '', false);
        $xml->writeElement('example', isset($exampleTab[$name]) ? myDecodeValue($exampleTab[$name]) : '', false);
        $xml->writeElement('style', $style);
        $xml->writeElement('disabled', $disabled);
        $xml->endElement();
        $nbArg++;
    }
}
$xml->writeElement('nbArg', $nbArg);
$xml->endElement();
header('Content-Type: text/xml');
header('Pragma: no-cache');
header('Expires: 0');
header('Cache-Control: no-cache, must-revalidate');
$xml->output();
