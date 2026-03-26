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

if (! isset($oreon)) {
    exit();
}

$checkboxGroup = ['backup_database_full', 'backup_database_partial'];
$DBRESULT = $pearDB->query("SELECT * FROM `options` WHERE options.key LIKE 'backup_%'");
while ($opt = $DBRESULT->fetchRow()) {
    if (in_array($opt['key'], $checkboxGroup)) {
        $values = explode(',', $opt['value']);
        foreach ($values as $value) {
            $gopt[$opt['key']][trim($value)] = 1;
        }
    } else {
        $gopt[$opt['key']] = myDecode($opt['value']);
    }
}
$DBRESULT->closeCursor();

$attrsText = ['size' => '40'];
$attrsText2 = ['size' => '3'];

// Form begin
$form = new HTML_QuickFormCustom('Form', 'post', '?p=' . $p);

// General Options
$backupEnabled = [];
$backupEnabled[] = $form->createElement('radio', 'backup_enabled', null, _('Yes'), '1');
$backupEnabled[] = $form->createElement('radio', 'backup_enabled', null, _('No'), '0');
$form->addGroup($backupEnabled, 'backup_enabled', _('Backup enabled'), '&nbsp;');
$form->setDefaults(['backup_enabled' => '0']);
$form->addElement('text', 'backup_backup_directory', _('Backup directory'), $attrsText);
$form->addRule('backup_backup_directory', _('Mandatory field'), 'required');
$form->addElement('text', 'backup_tmp_directory', _('Temporary directory'), $attrsText);
$form->addRule('backup_tmp_directory', _('Mandatory field'), 'required');

// Database Options
$form->addElement('checkbox', 'backup_database_centreon', _('Backup database centreon'));
$form->addElement('checkbox', 'backup_database_centreon_storage', _('Backup database centreon_storage'));
$backupDatabaseType = [];
$backupDatabaseType[] = $form->createElement('radio', 'backup_database_type', null, _('Dump'), '0');
$backupDatabaseType[] = $form->createElement('radio', 'backup_database_type', null, _('LVM Snapshot'), '1');
$form->addGroup($backupDatabaseType, 'backup_database_type', _('Backup type'), '&nbsp;');
$form->setDefaults(['backup_database_type' => '1']);
$backupDatabasePeriodFull[] = $form->createElement('checkbox', '1', '&nbsp;', _('Monday'));
$backupDatabasePeriodFull[] = $form->createElement('checkbox', '2', '&nbsp;', _('Tuesday'));
$backupDatabasePeriodFull[] = $form->createElement('checkbox', '3', '&nbsp;', _('Wednesday'));
$backupDatabasePeriodFull[] = $form->createElement('checkbox', '4', '&nbsp;', _('Thursday'));
$backupDatabasePeriodFull[] = $form->createElement('checkbox', '5', '&nbsp;', _('Friday'));
$backupDatabasePeriodFull[] = $form->createElement('checkbox', '6', '&nbsp;', _('Saturday'));
$backupDatabasePeriodFull[] = $form->createElement('checkbox', '0', '&nbsp;', _('Sunday'));
$form->addGroup($backupDatabasePeriodFull, 'backup_database_full', _('Full backup'), '&nbsp;&nbsp;');
$backupDatabasePeriodPartial[] = $form->createElement('checkbox', '1', '&nbsp;', _('Monday'));
$backupDatabasePeriodPartial[] = $form->createElement('checkbox', '2', '&nbsp;', _('Tuesday'));
$backupDatabasePeriodPartial[] = $form->createElement('checkbox', '3', '&nbsp;', _('Wednesday'));
$backupDatabasePeriodPartial[] = $form->createElement('checkbox', '4', '&nbsp;', _('Thursday'));
$backupDatabasePeriodPartial[] = $form->createElement('checkbox', '5', '&nbsp;', _('Friday'));
$backupDatabasePeriodPartial[] = $form->createElement('checkbox', '6', '&nbsp;', _('Saturday'));
$backupDatabasePeriodPartial[] = $form->createElement('checkbox', '0', '&nbsp;', _('Sunday'));
$form->addGroup($backupDatabasePeriodPartial, 'backup_database_partial', _('Partial backup'), '&nbsp;&nbsp;');
$form->addElement('text', 'backup_retention', _('Backup retention'), $attrsText2);
$form->addRule('backup_retention', _('Mandatory field'), 'required');
$form->addRule('backup_retention', _('Must be a number'), 'numeric');

// Configuration Files Options
$form->addElement('checkbox', 'backup_configuration_files', _('Backup configuration files'));
$form->addElement('text', 'backup_mysql_conf', _('MySQL configuration file path'), $attrsText);

// Export Options
$scpEnabled = [];
$scpEnabled[] = $form->createElement('radio', 'backup_export_scp_enabled', null, _('Yes'), '1');
$scpEnabled[] = $form->createElement('radio', 'backup_export_scp_enabled', null, _('No'), '0');
$form->addGroup($scpEnabled, 'backup_export_scp_enabled', _('SCP export enabled'), '&nbsp;');
$form->setDefaults(['backup_export_scp_enabled' => '0']);
$form->addElement('text', 'backup_export_scp_user', _('Remote user'), $attrsText);
$form->addElement('text', 'backup_export_scp_host', _('Remote host'), $attrsText);
$form->addElement('text', 'backup_export_scp_directory', _('Remote directory'), $attrsText);
$form->addElement('hidden', 'gopt_id');
$redirect = $form->addElement('hidden', 'o');
$redirect->setValue($o);

$form->applyFilter('__ALL__', 'myTrim');

$form->setDefaults($gopt);

$form->addElement('submit', 'submitC', _('Save'), ['class' => 'btc bt_success']);
$form->addElement('reset', 'reset', _('Reset'), ['class' => 'btc bt_default']);

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path . '/backup');

// prepare help texts
$helptext = '';
include_once 'help.php';
foreach ($help as $key => $text) {
    $helptext .= '<span style="display:none" id="help:' . $key . '">' . $text . '</span>' . "\n";
}
$tpl->assign('helptext', $helptext);

$valid = false;
if ($form->validate()) {
    // Update in DB
    updateBackupConfigData($pearDB, $form, $oreon);

    $o = null;
    $valid = true;
    $form->freeze();
}
if (! $form->validate() && isset($_POST['gopt_id'])) {
    echo "<div class='msg' align='center'>" . _('impossible to validate, one or more field is incorrect') . '</div>';
}

$form->addElement(
    'button',
    'change',
    _('Modify'),
    ['onClick' => "javascript:window.location.href='?p=" . $p . "&o=backup'", 'class' => 'btc bt_info']
);

// Apply a template definition
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
$renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->assign('o', $o);
$tpl->assign('valid', $valid);

$tpl->display('formBackup.html');
