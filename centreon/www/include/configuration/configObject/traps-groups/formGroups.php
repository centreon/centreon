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

//
// # Database retrieve information for Manufacturer
//

function myDecodeGroup($arg)
{
    return html_entity_decode($arg ?? '', ENT_QUOTES, 'UTF-8');
}

$group = [];
if (($o == 'c' || $o == 'w') && $id) {
    $query = 'SELECT traps_group_name as name, traps_group_id as id FROM traps_group '
        . "WHERE traps_group_id = '" . $pearDB->escape($id) . "' LIMIT 1";
    $DBRESULT = $pearDB->query($query);
    // Set base value
    $group = array_map('myDecodeGroup', $DBRESULT->fetchRow());
    $DBRESULT->closeCursor();
}

// #########################################################
// Var information to format the element
//
$attrsText = ['size' => '50'];
$attrsTextarea = ['rows' => '5', 'cols' => '40'];
//
// # Form begin
//
$form = new HTML_QuickFormCustom('Form', 'post', '?p=' . $p);
if ($o == 'a') {
    $form->addElement('header', 'title', _('Add Group'));
} elseif ($o == 'c') {
    $form->addElement('header', 'title', _('Modify Group'));
} elseif ($o == 'w') {
    $form->addElement('header', 'title', _('View Group'));
}

//
// # Group information
//
$form->addElement('text', 'name', _('Name'), $attrsText);

$avRoute = './include/common/webServices/rest/internal.php?object=centreon_configuration_trap&action=list';
$deRoute = './include/common/webServices/rest/internal.php?object=centreon_configuration_trap'
    . '&action=defaultValues&target=Traps&field=groups&id=' . $id;
$attrTraps = ['datasourceOrigin' => 'ajax', 'availableDatasetRoute' => $avRoute, 'multiple' => true, 'linkedObject' => 'centreonTraps', 'defaultDatasetRoute' => $deRoute];
$form->addElement('select2', 'traps', _('Traps'), [], $attrTraps);

//
// # Further informations
//
$form->addElement('hidden', 'id');
$redirect = $form->addElement('hidden', 'o');
$redirect->setValue($o);

//
// # Form Rules
//
$form->applyFilter('__ALL__', 'myTrim');
$form->addRule('name', _('Compulsory Name'), 'required');
$form->registerRule('exist', 'callback', 'testTrapGroupExistence');
$form->addRule('name', _('Name is already in use'), 'exist');
$form->setRequiredNote("<font style='color: red;'>*</font>&nbsp;" . _('Required fields'));

//
// #End of form definition
//

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);

$tpl->assign(
    'helpattr',
    'TITLE, "' . _('Help') . '", CLOSEBTN, true, FIX, [this, 0, 5], BGCOLOR, "#ffff99", BORDERCOLOR, "orange", '
    . 'TITLEFONTCOLOR, "black", TITLEBGCOLOR, "orange", CLOSEBTNCOLORS, ["","black", "white", "red"], WIDTH, '
    . '-300, SHADOW, true, TEXTALIGN, "justify"'
);

// prepare help texts
$helptext = '';
include_once 'help.php';
foreach ($help as $key => $text) {
    $helptext .= '<span style="display:none" id="help:' . $key . '">' . $text . '</span>' . "\n";
}
$tpl->assign('helptext', $helptext);

// Just watch a Trap Group information
if ($o == 'w') {
    if ($centreon->user->access->page($p) != 2) {
        $form->addElement(
            'button',
            'change',
            _('Modify'),
            ['onClick' => "javascript:window.location.href='?p=" . $p . '&o=c&id=' . $id . "'"]
        );
    }
    $form->setDefaults($group);
    $form->freeze();
} // Modify a Trap Group information
elseif ($o == 'c') {
    $subC = $form->addElement('submit', 'submitC', _('Save'), ['class' => 'btc bt_success']);
    $res = $form->addElement('reset', 'reset', _('Reset'), ['class' => 'btc bt_default']);
    $form->setDefaults($group);
} // Add a Trap Group information
elseif ($o == 'a') {
    $subA = $form->addElement('submit', 'submitA', _('Save'), ['class' => 'btc bt_success']);
    $res = $form->addElement('reset', 'reset', _('Reset'), ['class' => 'btc bt_default']);
}

$valid = false;
if ($form->validate()) {
    $trapGroupObj = $form->getElement('id');
    if ($form->getSubmitValue('submitA')) {
        $trapGroupObj->setValue(insertTrapGroupInDB());
    } elseif ($form->getSubmitValue('submitC')) {
        updateTrapGroupInDB($trapGroupObj->getValue());
    }
    $o = null;
    $valid = true;
}

if ($valid) {
    require_once $path . 'listGroups.php';
} else {
    // #Apply a template definition
    $renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
    $renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
    $renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
    $form->accept($renderer);
    $tpl->assign('form', $renderer->toArray());
    $tpl->assign('o', $o);
    $tpl->display('formGroups.ihtml');
}
