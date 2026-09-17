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

if (! $oreon->user->admin) {
    if ($sc_id && $scString != "''" && ! str_contains($scString, "'" . $sc_id . "'")) {
        $msg = new CentreonMsg();
        $msg->setImage('./img/icons/warning.png');
        $msg->setTextStyle('bold');
        $msg->setText(_('You are not allowed to access this service category'));

        return null;
    }
}

// Database retrieve information for Contact
$cct = [];
if (($o == 'c' || $o == 'w') && $sc_id) {
    $DBRESULT = $pearDB->prepare('SELECT * FROM `service_categories` WHERE `sc_id` = :sc_id LIMIT 1');
    $DBRESULT->bindValue(':sc_id', $sc_id, PDO::PARAM_INT);
    $DBRESULT->execute();
    // Set base value
    $sc = array_map('myDecode', $DBRESULT->fetchRow());
    $DBRESULT->closeCursor();
    $sc['sc_severity_level'] = $sc['level'];
    $sc['sc_severity_icon'] = $sc['icon_id'];

    $sc['sc_svc'] = [];
}

// Define Template
$attrsText = ['size' => '30'];
$attrsText2 = ['size' => '60'];
$attrsAdvSelect = ['style' => 'width: 300px; height: 150px;'];
$attrsTextarea = ['rows' => '5', 'cols' => '40'];
$eTemplate = '<table><tr><td><div class="ams">{label_2}</div>{unselected}</td><td align="center">{add}<br /><br />'
    . '<br />{remove}</td><td><div class="ams">{label_3}</div>{selected}</td></tr></table>';

$servTplAvRoute = './include/common/webServices/rest/internal.php?object=centreon_configuration_servicetemplate'
    . '&action=list';
$attrServicetemplates = ['datasourceOrigin' => 'ajax', 'availableDatasetRoute' => $servTplAvRoute, 'multiple' => true, 'linkedObject' => 'centreonServicetemplates'];

// Form begin
$form = new HTML_QuickFormCustom('Form', 'post', '?p=' . $p);
if ($o == 'a') {
    $form->addElement('header', 'title', _('Add a Service Category'));
} elseif ($o == 'c') {
    $form->addElement('header', 'title', _('Modify a Service Category'));
} elseif ($o == 'w') {
    $form->addElement('header', 'title', _('View a Service Category'));
}

// Contact basic information
$form->addElement('header', 'information', _('Information'));
$form->addElement('header', 'links', _('Relations'));

// No possibility to change name and alias, because there's no interest
$form->addElement('text', 'sc_name', _('Name'), $attrsText);
$form->addElement('text', 'sc_description', _('Description'), $attrsText);

// Severity
$sctype = $form->addElement('checkbox', 'sc_type', _('Severity type'), null, ['id' => 'sc_type']);
if (isset($sc_id, $sc['level'])   && $sc['level'] != '') {
    $sctype->setValue('1');
}
$form->addElement('text', 'sc_severity_level', _('Level'), ['size' => '10']);
$iconImgs = return_image_list(1);
$form->addElement('select', 'sc_severity_icon', _('Icon'), $iconImgs, ['id' => 'icon_id', 'onChange' => "showLogo('icon_id_ctn', this.value)", 'onkeyup' => 'this.blur(); this.focus();']);

$servTplDeRoute = './include/common/webServices/rest/internal.php?object=centreon_configuration_servicetemplate'
    . '&action=defaultValues&target=servicecategories&field=sc_svcTpl&id=' . $sc_id;
$attrServicetemplate1 = array_merge(
    $attrServicetemplates,
    ['defaultDatasetRoute' => $servTplDeRoute]
);

$form->addElement('select2', 'sc_svcTpl', _('Linked Templates'), [], $attrServicetemplate1);

$sc_activate[] = $form->createElement('radio', 'sc_activate', null, _('Enabled'), '1');
$sc_activate[] = $form->createElement('radio', 'sc_activate', null, _('Disabled'), '0');
$form->addGroup($sc_activate, 'sc_activate', _('Status'), '&nbsp;');
$form->setDefaults(['sc_activate' => '1']);

$form->addElement('hidden', 'sc_id');
$redirect = $form->addElement('hidden', 'o');
$redirect->setValue($o);

if (is_array($select)) {
    $select_str = null;
    foreach ($select as $key => $value) {
        $select_str .= $key . ',';
    }
    $select_pear = $form->addElement('hidden', 'select');
    $select_pear->setValue($select_str);
}

// Form Rules
function myReplace()
{
    global $form;
    $ret = $form->getSubmitValues();

    return str_replace(' ', '_', $ret['contact_name']);
}

$form->applyFilter('__ALL__', 'myTrim');
$form->applyFilter('contact_name', 'myReplace');
$from_list_menu = false;

$form->addRule('sc_name', _('Compulsory Name'), 'required');
$form->addRule('sc_description', _('Compulsory Alias'), 'required');

$form->registerRule('existName', 'callback', 'testServiceCategorieExistence');
$form->addRule('sc_name', _('Name is already in use'), 'existName');

$form->addRule('sc_severity_level', _('Must be a number'), 'numeric');

$form->registerRule('shouldNotBeEqTo0', 'callback', 'shouldNotBeEqTo0');
$form->addRule('sc_severity_level', _("Can't be equal to 0"), 'shouldNotBeEqTo0');

$form->addFormRule('checkSeverity');

$form->setRequiredNote("<font style='color: red;'>*</font>&nbsp;" . _('Required fields'));

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);

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

if ($o == 'w') {
    // Just watch a service_categories information
    if ($centreon->user->access->page($p) != 2) {
        $form->addElement(
            'button',
            'change',
            _('Modify'),
            ['onClick' => "javascript:window.location.href='?p=" . $p . '&o=c&sc_id=' . $sc_id . "'"]
        );
    }
    $form->setDefaults($sc);
    $form->freeze();
} elseif ($o == 'c') {
    // Modify a service_categories information
    $subC = $form->addElement('submit', 'submitC', _('Save'), ['class' => 'btc bt_success']);
    $res = $form->addElement('reset', 'reset', _('Reset'), ['class' => 'btc bt_default']);
    $form->setDefaults($sc);
} elseif ($o == 'a') {
    // Add a service_categories information
    $subA = $form->addElement('submit', 'submitA', _('Save'), ['class' => 'btc bt_success']);
    $res = $form->addElement('reset', 'reset', _('Reset'), ['class' => 'btc bt_default']);
}

$valid = false;
if ($form->validate() && $from_list_menu == false) {
    $cctObj = $form->getElement('sc_id');
    if ($form->getSubmitValue('submitA')) {
        $cctObj->setValue(insertServiceCategorieInDB());
    } elseif ($form->getSubmitValue('submitC')) {
        updateServiceCategorieInDB();
    }
    $o = null;
    $valid = true;
}

if ($valid) {
    require_once $path . 'listServiceCategories.php';
} else {
    // Apply a template definition
    $renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl, true);
    $renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
    $renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');

    $form->accept($renderer);
    $tpl->assign('form', $renderer->toArray());
    $tpl->assign('o', $o);
    $tpl->assign('p', $p);
    $tpl->display('formServiceCategories.ihtml');
}
