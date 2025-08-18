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

const DOWNTIME_ON_HOST = 75;

$select = [];
if (isset($_GET['select'])) {
    foreach ($_GET['select'] as $key => $value) {
        if ((int) $cmd == DOWNTIME_ON_HOST) {
            $tmp = preg_split("/\;/", urlencode($key));
            $select[] = $tmp[0];
        } else {
            $select[] = urlencode($key);
        }
    }
}

// Smarty template initialization
$path = _CENTREON_PATH_ . '/www/include/monitoring/external_cmd/popup/';
$tpl = SmartyBC::createSmartyTemplate($path, './templates/');

$form = new HTML_QuickFormCustom('select_form', 'GET', 'main.php');

$form->addElement(
    'header',
    'title',
    _('Set downtimes')
);

$tpl->assign('authorlabel', _('Alias'));
$tpl->assign('authoralias', $centreon->user->get_alias());

$form->addElement(
    'textarea',
    'comment',
    _('Comment'),
    ['rows' => '5', 'cols' => '70', 'id' => 'popupComment']
);
$form->setDefaults(['comment' => sprintf(_('Downtime set by %s'), $centreon->user->alias)]);

$form->addElement(
    'text',
    'start',
    _('Start Time'),
    ['id' => 'start', 'size' => 10, 'class' => 'datepicker']
);
$form->addElement(
    'text',
    'end',
    _('End Time'),
    ['id' => 'end', 'size' => 10, 'class' => 'datepicker']
);

$form->addElement(
    'text',
    'start_time',
    '',
    ['id' => 'start_time', 'size' => 5, 'class' => 'timepicker']
);
$form->addElement(
    'text',
    'end_time',
    '',
    ['id' => 'end_time', 'size' => 5, 'class' => 'timepicker']
);

$form->addElement(
    'text',
    'timezone_warning',
    _('*The timezone used is configured on your user settings')
);

$form->addElement(
    'text',
    'duration',
    _('Duration'),
    ['id' => 'duration', 'width' => '30', 'disabled' => 'true']
);
// setting default values
$defaultDuration = 7200;
$defaultScale = 's';
// overriding the default duration and scale by the user's value from the administration fields
if (
    isset($centreon->optGen['monitoring_dwt_duration'])
    && $centreon->optGen['monitoring_dwt_duration']
) {
    $defaultDuration = $centreon->optGen['monitoring_dwt_duration'];
    if (
        isset($centreon->optGen['monitoring_dwt_duration_scale'])
        && $centreon->optGen['monitoring_dwt_duration_scale']
    ) {
        $defaultScale = $centreon->optGen['monitoring_dwt_duration_scale'];
    }
}
$form->setDefaults(['duration' => $defaultDuration]);

$scaleChoices = ['s' => _('Seconds'), 'm' => _('Minutes'), 'h' => _('Hours'), 'd' => _('Days')];
$form->addElement(
    'select',
    'duration_scale',
    _('Scale of time'),
    $scaleChoices,
    ['id' => 'duration_scale', 'disabled' => 'true']
);
$form->setDefaults(['duration_scale' => $defaultScale]);

$chckbox[] = $form->addElement(
    'checkbox',
    'fixed',
    _('Fixed'),
    '',
    ['id' => 'fixed']
);
$chckbox[0]->setChecked(true);

$chckbox2[] = $form->addElement(
    'checkbox',
    'downtimehostservice',
    _('Set downtimes on services attached to hosts'),
    '',
    ['id' => 'downtimehostservice']
);
$chckbox2[0]->setChecked(true);

$form->addElement(
    'hidden',
    'author',
    $centreon->user->get_alias(),
    ['id' => 'author']
);

$form->addRule(
    'comment',
    _('Comment is required'),
    'required',
    '',
    'client'
);
$form->setJsWarnings(_('Invalid information entered'), _('Please correct these fields'));

$form->addElement(
    'button',
    'submit',
    _('Set downtime'),
    ['onClick' => 'send_the_command();', 'class' => 'btc bt_info']
);
$form->addElement(
    'reset',
    'reset',
    _('Reset'),
    ['class' => 'btc bt_default']
);

// adding hidden fields to get the result of datepicker in an unlocalized format
// required for the external command to be send to centreon-engine
$form->addElement(
    'hidden',
    'alternativeDateStart',
    '',
    ['size' => 10, 'class' => 'alternativeDate']
);
$form->addElement(
    'hidden',
    'alternativeDateEnd',
    '',
    ['size' => 10, 'class' => 'alternativeDate']
);

$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
$renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');

$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());

$defaultFixed = '';
if (isset($centreon->optGen['monitoring_dwt_fixed'])
    && $centreon->optGen['monitoring_dwt_fixed']
) {
    $defaultFixed = 'checked';
}
$tpl->assign('defaultFixed', $defaultFixed);

$defaultSetDwtOnSvc = '';
if (isset($centreon->optGen['monitoring_dwt_svc'])
    && $centreon->optGen['monitoring_dwt_svc']
) {
    $defaultSetDwtOnSvc = 'checked';
}
$tpl->assign('defaultSetDwtOnSvc', $defaultSetDwtOnSvc);

$tpl->assign('o', $o);
$tpl->assign('p', $p);
$tpl->assign('cmd', $cmd);
$tpl->assign('select', $select);
$tpl->display('massive_downtime.ihtml');
