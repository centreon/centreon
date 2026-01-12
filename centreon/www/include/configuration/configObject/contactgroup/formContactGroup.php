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

if (! $centreon->user->admin && $cg_id) {
    $aclOptions = ['fields' => ['cg_id', 'cg_name'], 'keys' => ['cg_id'], 'get_row' => 'cg_name', 'conditions' => ['cg_id' => $cg_id]];
    $cgs = $acl->getContactGroupAclConf($aclOptions);
    if (! count($cgs)) {
        $msg = new CentreonMsg();
        $msg->setImage('./img/icons/warning.png');
        $msg->setTextStyle('bold');
        $msg->setText(_('You are not allowed to access this contact group'));

        return null;
    }
}

$initialValues = [];

// Database retrieve information for Contact
$cg = [];
if (($o == 'c' || $o == 'w') && $cg_id) {
    // Get host Group information
    $statement = $pearDB->prepare('SELECT * FROM `contactgroup` WHERE `cg_id` = :cg_id LIMIT 1');
    $statement->bindValue(':cg_id', (int) $cg_id, PDO::PARAM_INT);
    $statement->execute();

    // Set base value
    $cg = array_map('myDecode', $statement->fetch(PDO::FETCH_ASSOC));
}

$attrsText = ['size' => '30'];
$attrsAdvSelect = ['style' => 'width: 300px; height: 100px;'];
$attrsTextarea = ['rows' => '5', 'cols' => '60'];
$eTemplate = '<table><tr><td><div class="ams">{label_2}</div>{unselected}</td><td align="center">{add}<br /><br />'
    . '<br />{remove}</td><td><div class="ams">{label_3}</div>{selected}</td></tr></table>';
$contactRoute = './include/common/webServices/rest/internal.php?object=centreon_configuration_contact&action=list';
$attrContacts = ['datasourceOrigin' => 'ajax', 'availableDatasetRoute' => $contactRoute, 'multiple' => true, 'linkedObject' => 'centreonContact'];
$aclgRoute = './include/common/webServices/rest/internal.php?object=centreon_administration_aclgroup&action=list';
$attrAclgroups = ['datasourceOrigin' => 'ajax', 'availableDatasetRoute' => $aclgRoute, 'multiple' => true, 'linkedObject' => 'centreonAclGroup'];

// form begin
$form = new HTML_QuickFormCustom('Form', 'post', '?p=' . $p);
if ($o == 'a') {
    $form->addElement('header', 'title', _('Add a Contact Group'));
} elseif ($o == 'c') {
    $form->addElement('header', 'title', _('Modify a Contact Group'));
} elseif ($o == 'w') {
    $form->addElement('header', 'title', _('View a Contact Group'));
}

// Contact basic information
$form->addElement('header', 'information', _('General Information'));
$form->addElement('text', 'cg_name', _('Contact Group Name'), $attrsText);
$form->addElement('text', 'cg_alias', _('Alias'), $attrsText);

// Contacts Selection
$form->addElement('header', 'notification', _('Relations'));
$contactRoute = './include/common/webServices/rest/internal.php?object=centreon_configuration_contact'
    . '&action=defaultValues&target=contactgroup&field=cg_contacts&id=' . $cg_id;
$attrContact1 = array_merge(
    $attrContacts,
    ['defaultDatasetRoute' => $contactRoute]
);
$form->addElement('select2', 'cg_contacts', _('Linked Contacts'), [], $attrContact1);

// Acl group selection
$aclRoute = './include/common/webServices/rest/internal.php?object=centreon_administration_aclgroup'
    . '&action=defaultValues&target=contactgroup&field=cg_acl_groups&id=' . $cg_id;
$attrAclgroup1 = array_merge(
    $attrAclgroups,
    ['defaultDatasetRoute' => $aclRoute]
);
$form->addElement('select2', 'cg_acl_groups', _('Linked ACL groups'), [], $attrAclgroup1);

// Further informations
$form->addElement('header', 'furtherInfos', _('Additional Information'));
$cgActivation[] = $form->createElement('radio', 'cg_activate', null, _('Enabled'), '1');
$cgActivation[] = $form->createElement('radio', 'cg_activate', null, _('Disabled'), '0');
$form->addGroup($cgActivation, 'cg_activate', _('Status'), '&nbsp;');
$form->setDefaults(['cg_activate' => '1']);
$form->addElement('textarea', 'cg_comment', _('Comments'), $attrsTextarea);

$form->addElement('hidden', 'cg_id');
$redirect = $form->addElement('hidden', 'o');
$redirect->setValue($o);
$init = $form->addElement('hidden', 'initialValues');
$init->setValue(serialize($initialValues));

// Set rules
$form->applyFilter('__ALL__', 'myTrim');
$form->addRule('cg_name', _('Compulsory Name'), 'required');
$form->addRule('cg_alias', _('Compulsory Alias'), 'required');

if (! $centreon->user->admin) {
    $form->addRule('cg_acl_groups', _('Compulsory field'), 'required');
}

$form->registerRule('exist', 'callback', 'testContactGroupExistence');
$form->addRule('cg_name', _('Name is already in use'), 'exist');
$form->setRequiredNote("<font style='color: red;'>*</font>&nbsp;" . _('Required fields'));

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);

$tpl->assign(
    'helpattr',
    'TITLE, "' . _('Help') . '", CLOSEBTN, true, FIX, [this, 0, 5], BGCOLOR, "#ffff99", BORDERCOLOR, '
    . '"orange", TITLEFONTCOLOR, "black", TITLEBGCOLOR, "orange", CLOSEBTNCOLORS, ["","black", "white", "red"],'
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
    // Just watch a Contact Group information
    if ($centreon->user->access->page($p) != 2) {
        $form->addElement(
            'button',
            'change',
            _('Modify'),
            ['onClick' => "javascript:window.location.href='?p=" . $p . '&o=c&cg_id=' . $cg_id . "'"]
        );
    }
    $form->setDefaults($cg);
    $form->freeze();
} elseif ($o == 'c') {
    // Modify a Contact Group information
    $subC = $form->addElement('submit', 'submitC', _('Save'), ['class' => 'btc bt_success']);
    $res = $form->addElement('reset', 'reset', _('Reset'), ['class' => 'btc bt_default']);
    $form->setDefaults($cg);
} elseif ($o == 'a') {
    // Add a Contact Group information
    $subA = $form->addElement('submit', 'submitA', _('Save'), ['class' => 'btc bt_success']);
    $res = $form->addElement('reset', 'reset', _('Reset'), ['class' => 'btc bt_default']);
}

$valid = false;
if ($form->validate()) {
    $cgObj = $form->getElement('cg_id');

    if ($form->getSubmitValue('submitA')) {
        $cgObj->setValue(insertContactGroupInDB());
    } elseif ($form->getSubmitValue('submitC')) {
        updateContactGroupInDB($cgObj->getValue());
    }

    $o = null;
    $valid = true;
}
if ($valid) {
    require_once $path . 'listContactGroup.php';
} else {
    $renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl, true);
    $renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
    $renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
    $form->accept($renderer);
    $tpl->assign('form', $renderer->toArray());
    $tpl->assign('o', $o);
    $tpl->display('formContactGroup.ihtml');
}
