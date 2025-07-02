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

require_once __DIR__ . '/formFunction.php';

$dbResult = $pearDB->query('SELECT * FROM `options`');
while ($opt = $dbResult->fetch()) {
    $gopt[$opt['key']] = myDecode($opt['value']);
}
$dbResult->closeCursor();

// Check value for interval_length
if (! isset($gopt['interval_length'])) {
    $gopt['interval_length'] = 60;
}

$attrsText = ['size' => '40'];
$attrsText2 = ['size' => '5'];
$attrsAdvSelect = null;

// Form begin
$form = new HTML_QuickFormCustom('Form', 'post', '?p=' . $p);
$form->addElement('header', 'title', _('Modify General Options'));

// Nagios information
$form->addElement('header', 'nagios', _('Monitoring Engine information'));
$form->addElement('text', 'nagios_path_plugins', _('Plugins Directory'), $attrsText);
$form->addElement('text', 'interval_length', _('Interval Length'), $attrsText2);
$form->addElement('text', 'mailer_path_bin', _('Directory + Mailer Binary'), $attrsText);

// Tactical Overview form
$limitArray = [];
for ($i = 10; $i <= 100; $i += 10) {
    $limitArray[$i] = $i;
}
$form->addElement('select', 'tactical_host_limit', _('Maximum number of hosts to show'), $limitArray);
$form->addElement('select', 'tactical_service_limit', _('Maximum number of services to show'), $limitArray);
$form->addElement('text', 'tactical_refresh_interval', _('Page refresh interval'), $attrsText2);

// Acknowledgement form
$form->addElement('checkbox', 'monitoring_ack_sticky', _('Sticky'));
$form->addElement('checkbox', 'monitoring_ack_notify', _('Notify'));
$form->addElement('checkbox', 'monitoring_ack_persistent', _('Persistent'));
$form->addElement('checkbox', 'monitoring_ack_active_checks', _('Force Active Checks'));
$form->addElement('checkbox', 'monitoring_ack_svc', _('Acknowledge services attached to hosts'));

// Downtime form
$form->addElement('checkbox', 'monitoring_dwt_fixed', _('Fixed'));
$form->addElement('checkbox', 'monitoring_dwt_svc', _('Set downtimes on services attached to hosts'));
$form->addElement('text', 'monitoring_dwt_duration', _('Duration'), $attrsText2);

$scaleChoices = ['s' => _('seconds'), 'm' => _('minutes'), 'h' => _('hours'), 'd' => _('days')];
$form->addElement('select', 'monitoring_dwt_duration_scale', _('Scale of time'), $scaleChoices);

$form->addElement('hidden', 'gopt_id');
$redirect = $form->addElement('hidden', 'o');
$redirect->setValue($o);

$form->applyFilter('__ALL__', 'myTrim');
$form->applyFilter('nagios_path', 'slash');
$form->applyFilter('nagios_path_plugins', 'slash');

$form->registerRule('is_valid_path', 'callback', 'is_valid_path');
$form->registerRule('is_readable_path', 'callback', 'is_readable_path');
$form->registerRule('is_executable_binary', 'callback', 'is_executable_binary');
$form->registerRule('is_writable_path', 'callback', 'is_writable_path');
$form->registerRule('is_writable_file', 'callback', 'is_writable_file');
$form->registerRule('is_writable_file_if_exist', 'callback', 'is_writable_file_if_exist');
$form->registerRule('isNum', 'callback', 'isNum');

$form->addRule('nagios_path_plugins', _("The directory isn't valid"), 'is_valid_path');
$form->addRule('tactical_refresh_interval', _('Refresh interval must be numeric'), 'numeric');

$form->addRule('interval_length', _('This value must be a numerical value.'), 'isNum');

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path . '/engine');

$form->setDefaults($gopt);

$subC = $form->addElement('submit', 'submitC', _('Save'), ['class' => 'btc bt_success']);
$dbResult = $form->addElement('reset', 'reset', _('Reset'), ['class' => 'btc bt_default']);

// prepare help texts
$helptext = '';
include_once 'help.php';
foreach ($help as $key => $text) {
    $helptext .= '<span style="display:none" id="help:' . $key . '">' . $text . '</span>' . "\n";
}
$tpl->assign('helptext', $helptext);

$valid = false;
$sessionKeyFreeze = 'administration-parameters-monitoring-freeze';

if ($form->validate()) {
    try {
        // Update in DB
        updateNagiosConfigData($form->getSubmitValue('gopt_id'));

        // Update in Centreon Object
        $oreon->initOptGen($pearDB);

        $o = null;
        $valid = true;

        /**
         * Freeze the form and reload the page
         */
        $form->freeze();
        $form->addElement(
            'button',
            'change',
            _('Modify'),
            ['onClick' => "javascript:window.location.href='?p=" . $p . "&o=engine'", 'class' => 'btc bt_info']
        );
        $_SESSION[$sessionKeyFreeze] = true;
        echo '<script>parent.location.href = "main.php?p=' . $p . '&o=engine";</script>';

        exit;
    } catch (Throwable $e) {
        echo "<div class='msg' align='center'>" . $e->getMessage() . '</div>';
        $valid = false;
    }
} elseif (array_key_exists($sessionKeyFreeze, $_SESSION) && $_SESSION[$sessionKeyFreeze] === true) {
    unset($_SESSION[$sessionKeyFreeze]);
    $form->addElement(
        'button',
        'change',
        _('Modify'),
        ['onClick' => "javascript:window.location.href='?p=" . $p . "&o=engine'", 'class' => 'btc bt_info']
    );
    $form->freeze();
    $valid = true;
}

if (! $form->validate() && isset($_POST['gopt_id'])) {
    echo "<div class='msg' align='center'>" . _('impossible to validate, one or more field is incorrect') . '</div>';
}

// Apply a template definition
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
$renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->assign('o', $o);
$tpl->assign('genOpt_nagios_version', _('Monitoring Engine'));
$tpl->assign('genOpt_dbLayer', _('Monitoring database layer'));
$tpl->assign('genOpt_nagios_direstory', _('Engine Directories'));
$tpl->assign('tacticalOverviewOptions', _('Tactical Overview'));
$tpl->assign('genOpt_mailer_path', _('Mailer path'));
$tpl->assign('genOpt_monitoring_properties', 'Monitoring properties');
$tpl->assign('acknowledgement_default_settings', _('Default acknowledgement settings'));
$tpl->assign('downtime_default_settings', _('Default downtime settings'));
$tpl->assign('seconds', _('seconds'));
$tpl->assign('valid', $valid);

$tpl->display('form.ihtml');
