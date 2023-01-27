<?php

/**
 * Copyright 2005-2021 Centreon
 * Centreon is developed by : Julien Mathis and Romain Le Merlus under
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
 **/

if (!isset($oreon)) {
    exit();
}

$pool = array();
if (($o == "c" || $o == "w") && $slot_id) {
    $dbResult = $pearDB->query("SELECT * FROM mod_dsm_pool WHERE pool_id = '" . $slot_id . "' LIMIT 1");
    $pool = $dbResult->fetch();
}

/*
 * Commands
 */
$Cmds = array();
$dbResult = $pearDB->query(
    "SELECT command_id, command_name FROM command WHERE command_type = '2' ORDER BY command_name"
);
while ($Cmd = $dbResult->fetch()) {
    $Cmds[$Cmd["command_id"]] = $Cmd["command_name"];
}

/*
 * pool hosts
 */
$poolHost = array();
$dbResult = $pearDB->query("SELECT host_id, host_name FROM host WHERE host_register = '1' ORDER BY host_name");
while ($data = $dbResult->fetch()) {
    $poolHost[$data["host_id"]] = $data["host_name"];
}

/*
 * pool service_template
 */
$poolST = array(null => null);
$dbResult = $pearDB->query(
    "SELECT service_id, service_description FROM service WHERE service_register = '0' ORDER BY service_description"
);
while ($data = $dbResult->fetch()) {
    $data["service_description"] = str_replace("#S#", "/", $data["service_description"]);
    $data["service_description"] = str_replace("#BS#", "\\", $data["service_description"]);
    $poolST[$data["service_id"]] = $data["service_description"];
}

/*
 * Template / Style for Quickform input
 */
$attrsText = array("size" => "30");
$attrsTextSmall = array("size" => "10");
$attrsText2 = array("size" => "60");
$attrsAdvSelect = array("style" => "width: 300px; height: 100px;");
$attrsTextarea = array("rows" => "5", "cols" => "40");
$template = "<table><tr><td>{unselected}</td><td align='center'>{add}<br /><br /><br />" .
    "{remove}</td><td>{selected}</td></tr></table>";

/*
 * Form begin
 */
$form = new HTML_QuickFormCustom('Form', 'post', "?p=" . $p);
if ($o == "a") {
    $form->addElement('header', 'title', _("Add a pool of services"));
} elseif ($o == "c") {
    $form->addElement('header', 'title', _("Modify a pool of services"));
} elseif ($o == "w") {
    $form->addElement('header', 'title', _("View a pool of services"));
}

/*
 * pool basic information
 */
$form->addElement('header', 'information', _("General Information"));
$form->addElement('header', 'slotInformation', _("Slots Information"));
$form->addElement('header', 'Notification', _("Notifications Information"));


/*
 * No possibility to change name and alias, because there's no interest
 */
$form->addElement('text', 'pool_name', _("Name"), $attrsText);
$form->addElement('text', 'pool_description', _("Description"), $attrsText);
$form->addElement('text', 'pool_number', _("Number of Slots"), $attrsTextSmall);
$form->addElement('text', 'pool_prefix', _("Slot name prefix"), $attrsText);
$form->addElement('select', 'pool_host_id', _("Host Name"), $poolHost);
$form->addElement('select', 'pool_cmd_id', _("Check commands"), $Cmds);
$form->addElement('text', 'pool_args', _("arguments"), $attrsText2);
$form->addElement('select', 'pool_service_template_id', _("Service template based"), $poolST);

/*
 * Further informations
 */
$form->addElement('header', 'furtherInfos', _("Additional Information"));
$poolActivation[] = $form->createElement('radio', 'pool_activate', null, _("Enabled"), '1');
$poolActivation[] = $form->createElement('radio', 'pool_activate', null, _("Disabled"), '0');
$form->addGroup($poolActivation, 'pool_activate', _("Status"), '&nbsp;');
$form->setDefaults(array('pool_activate' => '1'));

$form->addElement('hidden', 'pool_id');
$redirect = $form->addElement('hidden', 'o');
$redirect->setValue($o);
if (is_array($select)) {
    $select_str = null;
    foreach ($select as $key => $value) {
        $select_str .= $key . ",";
    }
    $select_pear = $form->addElement('hidden', 'select');
    $select_pear->setValue($select_str);
}

/*
 * Form Rules
 */
function myReplace()
{
    global $form;
    $ret = $form->getSubmitValues();
    return (str_replace(" ", "_", $ret["pool_name"]));
}

$form->applyFilter('__ALL__', 'myTrim');
$form->applyFilter('pool_name', 'myReplace');
$from_list_menu = false;
if ($o != "mc") {
    $form->addRule('pool_name', _("Compulsory Name"), 'required');
    $form->addRule('pool_host_id', _("Compulsory Alias"), 'required');
    $form->addRule('pool_prefix', _("Compulsory Alias"), 'required');
    $form->addRule('pool_number', _("Compulsory Alias"), 'required');
} elseif ($o == "mc") {
    if ($form->getSubmitValue("submitMC")) {
        $from_list_menu = false;
    } else {
        $from_list_menu = true;
    }
}
$form->setRequiredNote("<font style='color: red;'>*</font>&nbsp;" . _("Required fields"));

/*
 * Smarty template Init
 */
$tpl = new Smarty();
$tpl = initSmartyTpl($path, $tpl);

if ($o == "w") {
    // Just watch a pool information
    $form->addElement(
        "button",
        "change",
        _("Modify"),
        array(
            "class" => "btc bt_default",
            "onClick" => "javascript:window.location.href='?p=" . $p . "&o=c&pool_id=" . $pool_id . "'"
        )
    );
    $form->setDefaults($pool);
    $form->freeze();
} elseif ($o == "c") {
    // Modify a pool information
    $subC = $form->addElement('submit', 'submitC', _("Save"), array("class" => "btc bt_success"));
    $res = $form->addElement('reset', 'reset', _("Reset"), array("class" => "btc bt_default"));
    $form->setDefaults($pool);
} elseif ($o == "a") {
    // Add a pool information
    $subA = $form->addElement('submit', 'submitA', _("Save"), array("class" => "btc bt_success"));
    $res = $form->addElement('reset', 'reset', _("Reset"), array("class" => "btc bt_default"));
}

$valid = false;
$msgErr = "";
if ($form->validate() && $from_list_menu == false) {
    $poolObj = $form->getElement('pool_id');
    if ($form->getSubmitValue("submitA")) {
        try {
            $pId = insertpoolInDB();
            $valid = true;
            $poolObj->setValue($pId);
        } catch (Exception $e) {
            $valid = false;
            $msgErr = $e->getMessage();
        }
    } elseif ($form->getSubmitValue("submitC")) {
        try {
            $valid = updatePoolInDB($poolObj->getValue());
        } catch (Exception $e) {
            $valid = false;
            $msgErr = $e->getMessage();
        }
    }
    $o = null;
    $form->addElement(
        "button",
        "change",
        _("Modify"),
        array(
            "class" => "btc bt_default",
            "onClick" => "javascript:window.location.href='?p=" . $p . "&o=c&pool_id=" . $poolObj->getValue() . "'"
        )
    );
    $form->freeze();
}

if ($valid) {
    include $path . "listSlot.php";
} else {
    /*
     * Apply a template definition
     */
    $renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
    $renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
    $renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
    $form->accept($renderer);
    $tpl->assign('form', $renderer->toArray());
    $tpl->assign('o', $o);
    $tpl->assign('msgErr', $msgErr);

    $helptext = "";
    include "help.php";
    foreach ($help as $key => $text) {
        $helptext .= '<span style="display:none" id="help:' . $key . '">' . $text . '</span>' . "\n";
    }
    $tpl->assign("helptext", $helptext);
    $tpl->display("formSlot.ihtml");
}
