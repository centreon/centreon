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

include './include/common/autoNumLimit.php';
require_once __DIR__ . '/listFunction.php';

$labels = ['name' => _('Name'), 'description' => _('Description'), 'status' => _('Status'), 'enabled' => _('Enabled'), 'disabled' => _('Disabled')];

$searchLdap = HtmlAnalyzer::sanitizeAndRemoveTags(
    $_POST['searchLdap'] ?? $_GET['searchLdap'] ?? null
);

$ldapConf = new CentreonLdapAdmin($pearDB);
if (isset($_POST['searchLdap']) || isset($_GET['searchLdap'])) {
    $centreon->historySearch = [];
    $centreon->historySearch[$url]['searchLdap'] = $searchLdap;
} else {
    $searchLdap = $centreon->historySearch[$url]['searchLdap'] ?? null;
}

$list = $ldapConf->getLdapConfigurationList($searchLdap);
$rows = count($list);

$enableLdap = 0;
foreach ($list as $k => $v) {
    if ($v['ar_enable']) {
        $enableLdap = 1;
    }
}
$statement = $pearDB->prepare("UPDATE options SET `value` = :value WHERE `key` = 'ldap_auth_enable'");
$statement->bindValue(':value', $enableLdap, PDO::PARAM_STR);
$statement->execute();

include './include/common/checkPagination.php';
$list = $ldapConf->getLdapConfigurationList($searchLdap, ($num * $limit), $limit);

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path . 'ldap/');

$form = new HTML_QuickFormCustom('select_form', 'POST', '?o=ldap&p=' . $p);

$attrBtnSuccess = ['class' => 'btc bt_success', 'onClick' => "window.history.replaceState('', '', '?p=" . $p . "&o=ldap');"];
$form->addElement('submit', 'Search', _('Search'), $attrBtnSuccess);

$tpl->assign('list', $list);
$tpl->assign(
    'msg',
    ['addL' => 'main.php?p=' . $p . '&o=ldap&new=1', 'addT' => _('Add'), 'delConfirm' => _('Do you confirm the deletion ?')]
);

$form->addElement(
    'select',
    'o1',
    null,
    [null => _('More actions...'), 'd' => _('Delete'), 'ms' => _('Enable'), 'mu' => _('Disable')],
    getActionList('o1')
);
$form->setDefaults(['o1' => null]);

$form->addElement(
    'select',
    'o2',
    null,
    [null => _('More actions...'), 'd' => _('Delete'), 'ms' => _('Enable'), 'mu' => _('Disable')],
    getActionList('o2')
);
$form->setDefaults(['o2' => null]);

$o1 = $form->getElement('o1');
$o1->setValue(null);
$o1->setSelected(null);
$o2 = $form->getElement('o2');
$o2->setValue(null);
$o2->setSelected(null);
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('limit', $limit);
$tpl->assign('form', $renderer->toArray());
$tpl->assign('labels', $labels);
$tpl->assign('searchLdap', $searchLdap);
$tpl->assign('p', $p);
$tpl->display('list.ihtml');
?>
<script type="text/javascript">
    function setA(_i) {
        document.forms['form'].elements['a'].value = _i;
    }
</script>
