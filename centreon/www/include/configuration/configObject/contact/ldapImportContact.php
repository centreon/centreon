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

require_once _CENTREON_PATH_ . 'www/class/centreonLDAP.class.php';

$attrsText = ['size' => '80'];
$attrsText2 = ['size' => '5'];

// Form begin
$form = new HTML_QuickFormCustom('Form', 'post', '?p=' . $p);
$form->addElement('header', 'title', _('LDAP Import'));

// Command information
$form->addElement('header', 'options', _('LDAP Servers'));

$form->addElement('text', 'ldap_search_filter', _('Search Filter'), $attrsText);
$form->addElement('header', 'result', _('Search Result'));
$form->addElement('header', 'ldap_search_result_output', _('Result'));

$link = 'LdapSearch()';
$form->addElement('button', 'ldap_search_button', _('Search'), ['class' => 'btc bt_success', 'onClick' => $link]);

$form->addElement('hidden', 'contact_id');
$redirect = $form->addElement('hidden', 'o');
$redirect->setValue($o);

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);

$tpl->assign(
    'ldap_search_filter_help',
    _('Active Directory :') . ' (&(objectClass=user)(samaccounttype=805306368)(objectCategory=person)(cn=*))<br />'
    . _('Lotus Domino :') . ' (&(objectClass=person)(cn=*))<br />' . _('OpenLDAP :') . ' (&(objectClass=person)(cn=*))'
);
$tpl->assign('ldap_search_filter_help_title', _('Filter Examples'));
$tpl->assign(
    'javascript',
    '<script type="text/javascript" src="./include/common/javascript/ContactAjaxLDAP/ajaxLdapSearch.js"></script>'
);

$query = "SELECT ar.ar_id, ar_name, REPLACE(ari_value, '%s', '*') as filter "
    . 'FROM auth_ressource ar '
    . 'LEFT JOIN auth_ressource_info ari ON ari.ar_id = ar.ar_id '
    . "WHERE ari.ari_name = 'user_filter' AND ar.ar_enable = '1' "
    . 'ORDER BY ar_name';
$res = $pearDB->query($query);
$ldapConfList = '';
while ($row = $res->fetch()) {
    if ($res->rowCount() == 1) {
        $ldapConfList .= "<input type='checkbox' name='ldapConf[" . $row['ar_id'] . "]'/ checked='true'> "
            . $row['ar_name'];
    } else {
        $ldapConfList .= "<input type='checkbox' name='ldapConf[" . $row['ar_id'] . "]'/> " . $row['ar_name'];
    }
    $ldapConfList .= '<br/>';
    $ldapConfList .= _('Filter') . ": <input size='80' type='text' value='" . $row['filter']
        . "' name='ldap_search_filter[" . $row['ar_id'] . "]'/>";
    $ldapConfList .= '<br/><br/>';
}

// List available contacts to choose which one we want to import
if ($o == 'li') {
    $subA = $form->addElement('submit', 'submitA', _('Import'), ['class' => 'btc bt_success']);
}

$valid = false;
if ($form->validate()) {
    if (isset($_POST['contact_select']['select']) && $form->getSubmitValue('submitA')) {
        // extracting the chosen contacts Id from the POST
        $selectedUsers = $_POST['contact_select']['select'];
        unset($_POST['contact_select']['select']);

        // removing the useless data sent
        $arrayToReturn = [];
        foreach ($_POST['contact_select'] as $key => $subKey) {
            $arrayToReturn[$key] = array_intersect_key($_POST['contact_select'][$key], $selectedUsers);
        }

        // restoring the filtered $_POST['contact_select']['select'] as it's needed in some DB-Func.php functions
        $arrayToReturn['select'] = $selectedUsers;
        $_POST['contact_select'] = $arrayToReturn;
        unset($selectedUsers, $arrayToReturn);

        insertLdapContactInDB($_POST['contact_select']);
    }
    $form->freeze();
    $valid = true;
}

if ($valid) {
    require_once $path . 'listContact.php';
} else {
    // Apply a template definition
    $renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
    $form->accept($renderer);
    $tpl->assign('ldapServers', _('Import from LDAP servers'));
    $tpl->assign('ldapConfList', $ldapConfList);
    $tpl->assign('form', $renderer->toArray());
    $tpl->assign('o', $o);
    $tpl->display('ldapImportContact.ihtml');
}
