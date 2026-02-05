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

if (! $centreon->user->admin && $centreon->user->access->checkAction('generate_trap') === 0) {
    require_once _CENTREON_PATH_ . 'www/include/core/errors/alt_error.php';

    return null;
}

// Init Centcore Pipe
$centcore_pipe = defined('_CENTREON_VARLIB_') ? _CENTREON_VARLIB_ . '/centcore.cmd' : '/var/lib/centreon/centcore.cmd';

// Get Poller List
$acl = $centreon->user->access;
$tab_nagios_server = $acl->getPollerAclConf(['get_row'    => 'name', 'order'      => ['name'], 'keys'       => ['id'], 'conditions' => ['ns_activate' => 1]]);

// Sort the list of poller server
$pollersId = isset($_GET['poller']) ? explode(',', $_GET['poller']) : [];

foreach ($tab_nagios_server as $key => $name) {
    if (in_array($key, $pollersId)) {
        $tab_nagios_server[$key] = $name;
    }
}

$n = count($tab_nagios_server);

// Display all server options
if ($n > 1) {
    foreach ($tab_nagios_server as $key => $name) {
        $tab_nagios_server[$key] = HtmlSanitizer::createFromString($name)->sanitize()->getString();
    }
    $tab_nagios_server = [0 => _('All Pollers')] + $tab_nagios_server;
}

// Form begin
$attrSelect = ['style' => 'width: 220px;'];

$form = new HTML_QuickFormCustom('Form', 'post', '?p=' . $p);
// Init Header for tables in template
$form->addElement('header', 'title', _('SNMP Trap Generation'));
$form->addElement('header', 'opt', _('Export Options'));
$form->addElement('header', 'result', _('Actions'));
$form->addElement('header', 'infos', _('Implied Server'));
$form->addElement('select', 'host', _('Poller'), $tab_nagios_server, $attrSelect);

// Add checkbox for enable restart
$form->addElement('checkbox', 'generate', _('Generate trap database '));
$form->addElement('checkbox', 'apply', _('Apply configurations'));

$options = [null => null, 'RELOADCENTREONTRAPD' => _('Reload'), 'RESTARTCENTREONTRAPD' => _('Restart')];
$form->addElement('select', 'signal', _('Send signal'), $options);

// Set checkbox checked.
$form->setDefaults(['generate' => '1', 'generate' => '1', 'opt' => '1']);

$redirect = $form->addElement('hidden', 'o');
$redirect->setValue($o);

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);

$sub = $form->addElement('submit', 'submit', _('Generate'), ['class' => 'btc bt_success']);
$msg = null;
$stdout = null;
$msg_generate = '';
$trapdPath = '/etc/snmp/centreon_traps/';

if ($form->validate()) {
    $ret = $form->getSubmitValues();
    $host_list = [];
    foreach ($tab_nagios_server as $key => $value) {
        if ($key && ($ret['host'] == 0 || $ret['host'] == $key)) {
            $host_list[$key] = $value;
        }
    }
    if ($ret['host'] == 0 || $ret['host'] != -1) {
        // Create Server List to snmptt generation file
        $tab_server = [];
        $query = 'SELECT `name`, `id`, `snmp_trapd_path_conf`, `localhost` FROM `nagios_server` '
            . "WHERE `ns_activate` = '1' ORDER BY `localhost` DESC";
        $DBRESULT_Servers = $pearDB->query($query);
        while ($tab = $DBRESULT_Servers->fetchRow()) {
            if (isset($ret['host']) && ($ret['host'] == 0 || $ret['host'] == $tab['id'])) {
                $tab_server[$tab['id']] = ['id' => $tab['id'], 'name' => $tab['name'], 'localhost' => $tab['localhost']];
            }
            if ($tab['localhost'] && $tab['snmp_trapd_path_conf']) {
                $trapdPath = $tab['snmp_trapd_path_conf'];
                // handle path traversal vulnerability
                if (str_contains($trapdPath, '..')) {
                    throw new Exception('Path traversal found');
                }
            }
        }
        if (isset($ret['generate']) && $ret['generate']) {
            $msg_generate .= sprintf('<strong>%s</strong><br/>', _('Database generation'));
            $stdout = '';
            foreach ($tab_server as $host) {
                if (! is_dir("{$trapdPath}/{$host['id']}")) {
                    mkdir("{$trapdPath}/{$host['id']}");
                }
                $filename = "{$trapdPath}/{$host['id']}/centreontrapd.sdb";
                $output = [];
                $returnVal = 0;
                exec(
                    escapeshellcmd(_CENTREON_PATH_ . "/bin/generateSqlLite '{$host['id']}' '{$filename}'") . ' 2>&1',
                    $output,
                    $returnVal
                );
                $stdout .= implode('<br/>', $output) . '<br/>';
                if ($returnVal != 0) {
                    break;
                }
            }
            $msg_generate .= str_replace("\n", '<br/>', $stdout) . '<br/>';
        }
        if (isset($ret['apply']) && $ret['apply'] && $returnVal == 0) {
            $msg_generate .= sprintf('<strong>%s</strong><br/>', _('Centcore commands'));
            foreach ($tab_server as $host) {
                passthru(
                    escapeshellcmd("echo 'SYNCTRAP:{$host['id']}'") . ' >> ' . escapeshellcmd($centcore_pipe),
                    $return
                );
                if ($return) {
                    $msg_generate .= "Error while writing into {$centcore_pipe}<br/>";
                } else {
                    $msg_generate .= "Poller (id:{$host['id']}): SYNCTRAP sent to centcore.cmd<br/>";
                }
            }
        }
        if (isset($ret['signal']) && in_array($ret['signal'], ['RELOADCENTREONTRAPD', 'RESTARTCENTREONTRAPD'])) {
            foreach ($tab_server as $host) {
                passthru(
                    escapeshellcmd("echo '{$ret['signal']}:{$host['id']}'") . ' >> ' . escapeshellcmd($centcore_pipe),
                    $return
                );
                if ($return) {
                    $msg_generate .= "Error while writing into {$centcore_pipe}<br/>";
                } else {
                    $msg_generate .= "Poller (id:{$host['id']}): {$ret['signal']} sent to centcore.cmd<br/>";
                }
            }
        }
    }
}

$form->addElement('header', 'status', _('Status'));
if (isset($msg) && $msg) {
    $tpl->assign('msg', $msg);
}
if (isset($msg_generate) && $msg_generate) {
    $tpl->assign('msg_generate', $msg_generate);
}
if (isset($tab_server) && $tab_server) {
    $tpl->assign('tab_server', $tab_server);
}
if (isset($host_list) && $host_list) {
    $tpl->assign('host_list', $host_list);
}

$tpl->assign(
    'helpattr',
    'TITLE, "' . _('Help') . '", CLOSEBTN, true, FIX, [this, 0, 5], BGCOLOR, "#ffff99", BORDERCOLOR, '
    . '"orange", TITLEFONTCOLOR, "black", TITLEBGCOLOR, "orange", CLOSEBTNCOLORS, ["","black", "white", "red"],'
    . 'WIDTH, -300, SHADOW, true, TEXTALIGN, "justify"'
);
$helptext = '';

include_once 'help.php';

foreach ($help as $key => $text) {
    $helptext .= '<span style="display:none" id="help:' . $key . '">' . $text . '</span>' . "\n";
}
$tpl->assign('helptext', $helptext);

// Apply a template definition
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
$renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->assign('o', $o);
$tpl->display('formGenerateTraps.ihtml');
