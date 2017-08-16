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

require_once _CENTREON_PATH_ . '/www/modules/api-web-import-export/api-web-import-export.conf.php';
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
