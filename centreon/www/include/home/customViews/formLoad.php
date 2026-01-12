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
    exit;
}

require_once _CENTREON_PATH_ . 'www/class/centreonCustomView.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonWidget.class.php';

$db = new CentreonDB();
$viewObj = new CentreonCustomView($centreon, $db);
$widgetObj = new CentreonWidget($centreon, $db);
$title = '';
$action = null;
$defaultTab = [];
if ($_REQUEST['action'] == 'load') {
    $title = _('Load a public view');
    $action = 'load';
}

if (! isset($action)) {
    echo _('No action');

    exit;
}

$query = 'select * from custom_views where public = 1';
$DBRES = $db->query($query);
$arrayView = [];
$arrayView[-1] = '';
while ($row = $DBRES->fetchRow()) {
    $arrayView[$row['custom_view_id']] = $row['name'];
}

// Smarty template initialization
$path = './include/home/customViews/';
$template = SmartyBC::createSmartyTemplate($path, './');

/**
 * Field templates
 */
$attrsText = ['size' => '30'];
$attrsAdvSelect = ['style' => 'width: 200px; height: 150px;'];
$attrsTextarea = ['rows' => '5', 'cols' => '40'];
$eTemplate = '<table><tr><td><div class="ams">{label_2}</div>{unselected}</td><td align="center">{add}<br /><br />'
    . '<br />{remove}</td><td><div class="ams">{label_3}</div>{selected}</td></tr></table>';

$form = new HTML_QuickFormCustom('Form', 'post', '?p=103');
$form->addElement('header', 'title', $title);
$form->addElement('header', 'information', _('General Information'));

$form->addElement('select', 'viewLoad', _('Public views list'), $arrayView);

/**
 * Submit button
 */
$form->addElement('button', 'submit', _('Submit'), ['onClick' => 'submitData();']);
$form->addElement('reset', 'reset', _('Reset'));
$form->addElement('hidden', 'action');
$form->setDefaults(['action' => $action]);

/**
 * Renderer
 */
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($template, true);
$renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
$renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
$form->accept($renderer);
$template->assign('form', $renderer->toArray());
$template->display('formLoad.ihtml');
?>
<script type="text/javascript">
    jQuery(function () {
        jQuery("input[type=text]").keypress(function (e) {
            var code = null;
            code = (e.keyCode ? e.keyCode : e.which);
            return (code == 13) ? false : true;
        });
    });

    function submitData() {
        jQuery.ajax({
            type: "POST",
            dataType: "xml",
            url: "./include/home/customViews/action.php",
            data: jQuery("#Form").serialize(),
            success: function (response) {
                var view = response.getElementsByTagName('custom_view_id');
                var error = response.getElementsByTagName('error');
                if (typeof(view) != 'undefined') {
                    var viewId = view.item(0).firstChild.data;
                    window.top.location = './main.php?p=103&currentView=' + viewId;
                } else if (typeof(error) != 'undefined') {
                    var errorMsg = error.item(0).firstChild.data;
                }
            }
        });
    }
</script>
