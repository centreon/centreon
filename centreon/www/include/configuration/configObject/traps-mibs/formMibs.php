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

// Debug Flag
$debug = 0;
$max_characters = 20000;

// Database retrieve information for Manufacturer

function myDecodeMib($arg)
{
    return html_entity_decode($arg ?? '', ENT_QUOTES, 'UTF-8');
}

// Init Formulary
$form = new HTML_QuickFormCustom('Form', 'post', '?p=' . $p);
$form->addElement('header', 'title', _('Import SNMP traps from MIB file'));

// Manufacturer information
$route = './include/common/webServices/rest/internal.php?object=centreon_configuration_manufacturer&action=list';
$attrManufacturer = ['datasourceOrigin' => 'ajax', 'availableDatasetRoute' => $route, 'multiple' => false, 'linkedObject' => 'centreonManufacturer'];

$form->addElement('select2', 'mnftr', _('Vendor Name'), [], $attrManufacturer);

$form->addElement('file', 'filename', _('File (.mib)'));

// Formulary Rules
$form->applyFilter('__ALL__', 'myTrim');
$form->addRule('mnftr', _('Compulsory Name'), 'required');
$form->addRule('filename', _('Compulsory Name'), 'required');
$form->setRequiredNote("<font style='color: red;'>*</font>&nbsp;" . _('Required fields'));

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($path);

$tpl->assign(
    'helpattr',
    'TITLE, "' . _('Help') . '", CLOSEBTN, true, FIX, [this, 0, 5], BGCOLOR, "#ffff99", BORDERCOLOR, "orange", '
    . 'TITLEFONTCOLOR, "black", TITLEBGCOLOR, "orange", CLOSEBTNCOLORS, ["","black", "white", "red"], WIDTH, -300, '
    . 'SHADOW, true, TEXTALIGN, "justify"'
);
// prepare help texts
$helptext = '';
include_once 'help.php';
foreach ($help as $key => $text) {
    $helptext .= '<span style="display:none" id="help:' . $key . '">' . $text . '</span>' . "\n";
}
$tpl->assign('helptext', $helptext);

// Just watch a Command information
$subA = $form->addElement('submit', 'submit', _('Import'), ['class' => 'btc bt_success']);
$form->addElement('header', 'status', _('Status'));
$msg = null;
$stdout = null;
if ($form->validate()) {
    $ret = $form->getSubmitValues();
    $fileObj = $form->getElement('filename');
    $manufacturerId = filter_var($ret['mnftr'], FILTER_VALIDATE_INT);

    if ($manufacturerId === false) {
        $tpl->assign('msg', 'Wrong manufacturer given.');
    } elseif ($fileObj->isUploadedFile()) {
        // Upload File
        $values = $fileObj->getValue();
        $msg .= str_replace("\n", '<br />', $stdout);
        $msg .= '<br />Moving traps in database...';

        $command = "@CENTREONTRAPD_BINDIR@/centFillTrapDB -f '" . $values['tmp_name']
            . "' -m " . $manufacturerId . ' --severity=info 2>&1';

        if ($debug) {
            echo $command;
        }

        $stdout = shell_exec($command);
        unlink($values['tmp_name']);

        if ($stdout === null) {
            $msg .= '<br />An error occured during generation.';
        } else {
            $msg .= '<br />' . str_replace('\n', '<br />', $stdout)
                . '<br />Generate Traps configuration files from Monitoring Engine configuration form!';
        }

        if (strlen($msg) > $max_characters) {
            $msg = substr($msg, 0, $max_characters) . '...'
                . sprintf(_('Message truncated (exceeded %s characters)'), $max_characters);
        }
        $tpl->assign('msg', $msg);
    }
}

// Apply a template definition
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
$renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->display('formMibs.ihtml');
