<?php
/*
 * Copyright 2005-2017 Centreon
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

if (!isset($oreon)) {
    exit();
}

require_once _CENTREON_PATH_ . '/www/modules/centreon-awie/centreon-awie.conf.php';
require_once _CENTREON_PATH_ . '/www/lib/HTML/QuickForm.php';
require_once _CENTREON_PATH_ . '/www/lib/HTML/QuickForm/Renderer/ArraySmarty.php';
//require_once _MODULE_PATH_ . 'core/help.php';

$export = realpath(dirname(__FILE__));
// Smarty template Init
$path = _MODULE_PATH_ . "/core/template/";
$tpl = new Smarty();
$tpl = initSmartyTpl($path, $tpl);
$form = new HTML_QuickForm('Form', 'post', "?p=" . $p);

$valid = false;
if ($form->validate()) {
    $valid = true;
    $form->freeze();
}

$form->addElement('header', 'title', _("Api Web Exporter"));

$exportAllOpt[] = HTML_QuickForm::createElement(
    'checkbox',
    'all',
    '&nbsp;',
    _("All"),
    array('id' => 'all', 'onClick' => 'selectAll(this);')
);
$form->addGroup($exportAllOpt, 'export_all', _("Export resources"), '&nbsp;&nbsp;');

$exportCmd[] = HTML_QuickForm::createElement('checkbox', 'c_cmd', '&nbsp;', _("Check CMD"));
$exportCmd[] = HTML_QuickForm::createElement('checkbox', 'n_cmd', '&nbsp;', _("Notification CMD"));
$exportCmd[] = HTML_QuickForm::createElement('checkbox', 'm_cmd', '&nbsp;', _("Misc CMD"));
$exportCmd[] = HTML_QuickForm::createElement('checkbox', 'd_cmd', '&nbsp;', _("Discovery CMD"));
$form->addGroup($exportCmd, 'export_cmd', '', '&nbsp;');

$exportOpt[] = HTML_QuickForm::createElement('checkbox', 'tp', '&nbsp;', _("Timeperiods"));
$exportOpt[] = HTML_QuickForm::createElement('checkbox', 'c', '&nbsp;', _("Contacts"));
$exportOpt[] = HTML_QuickForm::createElement('checkbox', 'cg', '&nbsp;', _("Contactgroups"));
$form->addGroup($exportOpt, 'simple_export', '', '&nbsp;');

$form->addElement('checkbox', 'host', '&nbsp;', _("Host"));
$form->addElement('text', 'host_filter', '', 120);

$form->addElement('checkbox', 'htpl', '&nbsp;', _("HTPL"));
$form->addElement('text', 'htpl_filter', '', 120);

$form->addElement('checkbox', 'host_c', '&nbsp;', _("Host Categories"));

$form->addElement('checkbox', 'svc', '&nbsp;', _("Services"));
$form->addElement('text', 'svc_filter', '', 120);

$form->addElement('checkbox', 'stpl', '&nbsp;', _("STPL"));
$form->addElement('text', 'stpl_filter', '', 120);

$form->addElement('checkbox', 'svc_c', '&nbsp;', _("Service Categories"));

$exportConnect[] = HTML_QuickForm::createElement('checkbox', 'acl', '&nbsp;', _("ACL"));
$exportConnect[] = HTML_QuickForm::createElement('checkbox', 'ldap', '&nbsp;', _("LDAP"));
$form->addGroup($exportConnect, 'export_connect', '', '&nbsp;');

$form->addElement('checkbox', 'poller', '&nbsp;', _("Poller"));
$form->addElement('text', 'poller_filter', '', 120);

$subC = $form->addElement('submit', 'submitC', _("Export"), array("class" => "btc bt_success"));
$res = $form->addElement('reset', 'reset', _("Reset"));

if ($valid) {
    $form->freeze();
}


$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl, true);
$form->accept($renderer);

/*
$helpText = "";
foreach ($help as $key => $text) {
    $helpText .= '<span style="display:none" id="help:' . $key . '">' . $text . '</span>' . "\n";
}
$tpl->assign("helpText", $helpText);
*/

$tpl->assign('form', $renderer->toArray());
$tpl->assign('valid', $valid);


$tpl->display($export . "/templates/formExport.tpl");
