<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

$tp = [];
if (($o == 'c' || $o == 'w') && $tp_id) {
    $dbResult = $pearDB->prepare('SELECT * FROM timeperiod WHERE tp_id = :tp_id LIMIT 1');
    $dbResult->bindValue(':tp_id', $tp_id, PDO::PARAM_INT);
    $dbResult->execute();

    // Set base value
    $tp = array_map('myDecode', $dbResult->fetchRow());
    $tp['contact_exclude'] = [];
}

$j = 0;

$dbResult = $pearDB->prepare(
    'SELECT exception_id, timeperiod_id, days, timerange
    FROM timeperiod_exceptions
    WHERE timeperiod_id = :tp_id ORDER BY `days`'
);
$dbResult->bindValue(':tp_id', $tp_id, PDO::PARAM_INT);
$dbResult->execute();

while ($exceptionTab = $dbResult->fetchRow()) {
    $exception_id[$j] = $exceptionTab['exception_id'];
    $exception_days[$j] = $exceptionTab['days'];
    $exception_timerange[$j] = $exceptionTab['timerange'];
    $exception_timeperiod_id[$j] = $exceptionTab['timeperiod_id'];
    $j++;
}
$dbResult->closeCursor();

// Var information to format the element
$attrsText = ['size' => '35'];
$attrsTextLong = ['size' => '55', 'maxlength' => '2048'];
$attrsAdvSelect = ['style' => 'width: 300px; height: 130px;'];
$eTemplate = '<table><tr><td><div class="ams">{label_2}</div>{unselected}</td><td align="center">{add}<br /><br />'
    . '<br />{remove}</td><td><div class="ams">{label_3}</div>{selected}</td></tr></table>';

$timeAvRoute = './include/common/webServices/rest/internal.php?object=centreon_configuration_timeperiod&action=list'
    . ($tp_id ? "&exclude={$tp_id}" : ''); // exclude this timeperiod from list
$attrTimeperiods = ['datasourceOrigin' => 'ajax', 'availableDatasetRoute' => $timeAvRoute, 'multiple' => true, 'linkedObject' => 'centreonTimeperiod'];

// Form begin
$form = new HTML_QuickFormCustom(
    'Form',
    'post',
    '?p=' . $p,
    '',
    ['onsubmit' => 'return formValidate()', 'data-centreon-validate' => '']
);
if ($o == 'a') {
    $form->addElement('header', 'title', _('Add a Time Period'));
} elseif ($o == 'c') {
    $form->addElement('header', 'title', _('Modify a Time Period'));
} elseif ($o == 'w') {
    $form->addElement('header', 'title', _('View a Time Period'));
}

// Time Period basic information
$form->addElement('header', 'information', _('General Information'));
$form->addElement('text', 'tp_name', _('Time Period Name'), $attrsText);
$form->addElement('text', 'tp_alias', _('Alias'), $attrsTextLong);

// Notification informations
$form->addElement('header', 'include', _('Extended Settings'));

$form->addElement('text', 'tp_sunday', _('Sunday'), $attrsTextLong);
$form->addElement('text', 'tp_monday', _('Monday'), $attrsTextLong);
$form->addElement('text', 'tp_tuesday', _('Tuesday'), $attrsTextLong);
$form->addElement('text', 'tp_wednesday', _('Wednesday'), $attrsTextLong);
$form->addElement('text', 'tp_thursday', _('Thursday'), $attrsTextLong);
$form->addElement('text', 'tp_friday', _('Friday'), $attrsTextLong);
$form->addElement('text', 'tp_saturday', _('Saturday'), $attrsTextLong);

// Include Timeperiod
$timeDeRoute = './include/common/webServices/rest/internal.php?object=centreon_configuration_timeperiod'
    . '&action=defaultValues&target=timeperiodRenderer&field=tp_include&id=' . $tp_id;
$attrTimeperiod1 = array_merge(
    $attrTimeperiods,
    ['defaultDatasetRoute' => $timeDeRoute]
);
$form->addElement('select2', 'tp_include', _('Timeperiod templates'), [], $attrTimeperiod1);

// Multiple exceptions relations stored in DB
$mTp = [];
$k = 0;

$DBRESULT = $pearDB->prepare('SELECT exception_id FROM timeperiod_exceptions WHERE timeperiod_id = :tp_id');
$DBRESULT->bindValue(':tp_id', $tp_id, PDO::PARAM_INT);
$DBRESULT->execute();

while ($multiTp = $DBRESULT->fetchRow()) {
    $mTp[$k] = $multiTp['exception_id'];
    $k++;
}

$DBRESULT->closeCursor();

// Include javascript for dynamique entries
require_once './include/configuration/configObject/timeperiod/timeperiod_JS.php';
if ($o == 'c' || $o == 'a' || $o == 'mc') {
    echo '<script type="text/javascript">';
    echo 'var tab = [];';

    for ($k = 0; isset($mTp[$k]); $k++) {
        echo "tab[{$k}] = " . $mTp[$k] . ';';
    }
    for ($k = 0; isset($exception_id[$k]); $k++) { ?>
            globalExceptionTabId[<?php echo $k; ?>] = <?php echo $exception_id[$k]; ?>;
            globalExceptionTabName[<?php echo $k; ?>] = '<?php echo $exception_days[$k]; ?>';
            globalExceptionTabTimerange[<?php echo $k; ?>] = '<?php echo $exception_timerange[$k]; ?>';
            globalExceptionTabTimeperiodId[<?php echo $k; ?>] = <?php echo $exception_timeperiod_id[$k]; ?>;
        <?php
    }
    echo '</script>';
}

// Further informations
$form->addElement('hidden', 'tp_id');
$redirect = $form->addElement('hidden', 'o');
$redirect->setValue($o);

// Form Rules
function myReplace()
{
    global $form;
    $ret = $form->getSubmitValues();

    return str_replace(' ', '_', $ret['tp_name']);
}

// Set rules
$form->applyFilter('__ALL__', 'myTrim');
$form->applyFilter('tp_name', 'myReplace');

$form->registerRule('exist', 'callback', 'testTPExistence');
$form->registerRule('format', 'callback', 'checkHours');

// Name Check
$form->addRule('tp_name', _('Compulsory Name'), 'required');
$form->addRule('tp_name', _('Name is already in use'), 'exist');
$form->addRule('tp_alias', _('Compulsory Alias'), 'required');

// Check Hours format
$form->addRule('tp_sunday', _('Error in hour definition'), 'format');
$form->addRule('tp_monday', _('Error in hour definition'), 'format');
$form->addRule('tp_tuesday', _('Error in hour definition'), 'format');
$form->addRule('tp_wednesday', _('Error in hour definition'), 'format');
$form->addRule('tp_thursday', _('Error in hour definition'), 'format');
$form->addRule('tp_friday', _('Error in hour definition'), 'format');
$form->addRule('tp_saturday', _('Error in hour definition'), 'format');

// Check for template loops
$form->registerRule('templateLoop', 'callback', 'testTemplateLoop');
$form->addRule('tp_include', _('The selected template has the same time period as a template'), 'templateLoop');

$form->setRequiredNote("<font style='color: red;'>*</font>&nbsp;" . _('Required fields'));

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);

if ($o == 'w') {
    // Just watch a Time Period information
    if ($centreon->user->access->page($p) != 2) {
        $form->addElement(
            'button',
            'change',
            _('Modify'),
            ['onClick' => "javascript:window.location.href='?p=" . $p . '&o=c&tp_id=' . $tp_id . "'"]
        );
    }
    $form->setDefaults($tp);
    $form->freeze();
} elseif ($o == 'c') {
    // Modify a Time Period information
    $subC = $form->addElement('submit', 'submitC', _('Save'), ['class' => 'btc bt_success']);
    $res = $form->addElement('reset', 'reset', _('Reset'), ['class' => 'btc bt_default']);
    $form->setDefaults($tp);
} elseif ($o == 'a') {
    // Add a Time Period information
    $subA = $form->addElement('submit', 'submitA', _('Save'), ['class' => 'btc bt_success']);
    $res = $form->addElement('reset', 'reset', _('Reset'), ['class' => 'btc bt_default']);
}

// Translations
$tpl->assign('tRDay', _('Days'));
$tpl->assign('tRHours', _('Time Range'));

$tpl->assign(
    'helpattr',
    'TITLE, "' . _('Help') . '", CLOSEBTN, true, FIX, [this, 0, 5], BGCOLOR, "#ffff99", BORDERCOLOR, "orange",'
    . ' TITLEFONTCOLOR, "black", TITLEBGCOLOR, "orange", CLOSEBTNCOLORS, ["","black", "white", "red"],'
    . ' WIDTH, -300, SHADOW, true, TEXTALIGN, "justify"'
);
// prepare help texts
$helptext = '';
include_once 'help.php';
foreach ($help as $key => $text) {
    $helptext .= '<span style="display:none" id="help:' . $key . '">' . $text . '</span>' . "\n";
}
$tpl->assign('helptext', $helptext);

$valid = false;
if ($form->validate()) {
    $tpObj = $form->getElement('tp_id');
    if ($form->getSubmitValue('submitA')) {
        if (null !== $timeperiodId = insertTimePeriodInAPI()) {
            $tpObj->setValue($timeperiodId);
            $o = null;
            $valid = true;
        }
    } elseif ($form->getSubmitValue('submitC')) {
        if (updateTimeperiodInAPI($tpObj->getValue())) {
            $o = null;
            $valid = true;
        }
    }
}

if ($valid) {
    require_once $path . 'listTimeperiod.php';
} else {
    // Apply a template definition
    $renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl, true);
    $renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
    $renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
    $form->accept($renderer);
    $tpl->assign('form', $renderer->toArray());
    $tpl->assign('o', $o);
    $tpl->assign('gmtUsed', $centreon->CentreonGMT->used());
    $tpl->assign('noExceptionMessage', _('GMT is activated on your system. Exceptions will not be generated.'));
    $tpl->assign('exceptionLabel', _('Exceptions'));
    $tpl->assign('countExceptions', $k);
    $tpl->display('formTimeperiod.ihtml');
}
