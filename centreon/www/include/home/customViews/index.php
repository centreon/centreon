<?php
/**
 * Copyright 2005-2015 Centreon
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

use Symfony\Component\HttpFoundation\Request;

require_once _CENTREON_PATH_ . 'www/class/centreonCustomView.class.php';
require_once _CENTREON_PATH_ . "www/class/centreonWidget.class.php";
require_once _CENTREON_PATH_ . "www/class/centreonContactgroup.class.php";

try {
    $db = new CentreonDB();
    $viewObj = new CentreonCustomView($centreon, $db);

    /*
     * Smarty
     */
    $path = "./include/home/customViews/";

    // Smarty template initialization
    $template = SmartyBC::createSmartyTemplate($path, "./");

    // Assign permissions and other variables to the template
    $aclEdit = $centreon->user->access->page('10301', true);
    $template->assign('aclEdit', $aclEdit);

    $aclShare = $centreon->user->access->page('10302', true);
    $template->assign('aclShare', $aclShare);

    $aclParameters = $centreon->user->access->page('10303', true);
    $template->assign('aclParameters', $aclParameters);

    $aclAddWidget = $centreon->user->access->page('10304', true);
    $template->assign('aclAddWidget', $aclAddWidget);

    $aclRotation = $centreon->user->access->page('10305', true);
    $template->assign('aclRotation', $aclRotation);

    $aclDeleteView = $centreon->user->access->page('10306', true);
    $template->assign('aclDeleteView', $aclDeleteView);

    $aclAddView = $centreon->user->access->page('10307', true);
    $template->assign('aclAddView', $aclAddView);

    $aclSetDefault = $centreon->user->access->page('10308', true);
    $template->assign('aclSetDefault', $aclSetDefault);

    $template->assign('editMode', _("Show/Hide edit mode"));

    $viewId = $viewObj->getCurrentView();
    $views = $viewObj->getCustomViews();

    $contactParameters = $centreon->user->getContactParameters($db, ['widget_view_rotation']);

    $rotationTimer = 0;
    if (isset($contactParameters['widget_view_rotation'])) {
        $rotationTimer = $contactParameters['widget_view_rotation'];
    }

    // Assign views to template
    $i = 1;
    $indexTab = [0 => -1];

    foreach ($views as $key => $val) {
        $indexTab[$key] = $i;
        $i++;
        $views[$key]['icon'] = !$viewObj->checkPermission($key) ? "locked" : "unlocked";
        $views[$key]['default'] = "";
        if ($viewObj->getDefaultViewId() == $key) {
            $views[$key]['default'] = '<span class="ui-icon ui-icon-star" style="float:left;"></span>';
        }
    }
    $template->assign('views', $views);
    $template->assign('empty', $i);

    $formAddView = new HTML_QuickFormCustom(
        'formAddView',
        'post',
        "?p=103",
        '_selft',
        ['onSubmit' => 'submitAddView(); return false;']
    );

    // List of shared views
    $arrayView = ['datasourceOrigin' => 'ajax', 'availableDatasetRoute' => './api/internal.php?object=centreon_home_customview&action=listSharedViews', 'multiple' => false];
    $formAddView->addElement('select2', 'viewLoad', _("Views"), [], $arrayView);

    // New view name
    $attrsText = ["size" => "30"];
    $formAddView->addElement('text', 'name', _("Name"), $attrsText);

    $createLoad = [];
    $createLoad[] = $formAddView->createElement('radio', 'create_load', null, _("Create new view "), 'create');
    $createLoad[] = $formAddView->createElement('radio', 'create_load', null, _("Load from existing view"), 'load');
    $formAddView->addGroup($createLoad, 'create_load', _("create or load"), '&nbsp;');
    $formAddView->setDefaults(['create_load[create_load]' => 'create']);

    /**
     * Layout
     */
    $layouts[] = $formAddView->createElement('radio', 'layout', null, _("1 Column"), 'column_1');
    $layouts[] = $formAddView->createElement('radio', 'layout', null, _("2 Columns"), 'column_2');
    $layouts[] = $formAddView->createElement('radio', 'layout', null, _("3 Columns"), 'column_3');
    $formAddView->addGroup($layouts, 'layout', _("Layout"), '&nbsp;');
    $formAddView->setDefaults(['layout[layout]' => 'column_1']);

    $formAddView->addElement('checkbox', 'public', '', _("Public"));

    /**
     * Submit button
     */
    $formAddView->addElement('submit', 'submit', _("Submit"), ["class" => "btc bt_success"]);
    $formAddView->addElement('reset', 'reset', _("Reset"), ["class" => "btc bt_default"]);
    $formAddView->addElement('hidden', 'action');
    $formAddView->setDefaults(['action' => 'add']);

    /**
     * Renderer
     */
    $rendererAddView = new HTML_QuickForm_Renderer_ArraySmarty($template, true);
    $rendererAddView->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
    $rendererAddView->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
    $formAddView->accept($rendererAddView);
    $template->assign('formAddView', $rendererAddView->toArray());

    /**
     * Form for edit view
     */
    $formEditView = new HTML_QuickFormCustom(
        'formEditView',
        'post',
        "?p=103",
        '',
        ['onSubmit' => 'submitEditView(); return false;']
    );

    /**
     * Name
     */
    $formEditView->addElement('text', 'name', _("Name"), $attrsText);

    /**
     * Layout
     */
    $layouts = [];
    $layouts[] = $formAddView->createElement('radio', 'layout', null, _("1 Column"), 'column_1');
    $layouts[] = $formAddView->createElement('radio', 'layout', null, _("2 Columns"), 'column_2');
    $layouts[] = $formAddView->createElement('radio', 'layout', null, _("3 Columns"), 'column_3');
    $formEditView->addGroup($layouts, 'layout', _("Layout"), '&nbsp;');
    $formEditView->setDefaults(['layout[layout]' => 'column_1']);

    $formEditView->addElement('checkbox', 'public', '', _("Public"));
    /**
     * Submit button
     */
    $formEditView->addElement('submit', 'submit', _("Submit"), ["class" => "btc bt_success"]);
    $formEditView->addElement('reset', 'reset', _("Reset"), ["class" => "btc bt_default"]);
    $formEditView->addElement('hidden', 'action');
    $formEditView->addElement('hidden', 'custom_view_id');
    $formEditView->setDefaults(['action' => 'edit']);

    /**
     * Renderer
     */
    $rendererEditView = new HTML_QuickForm_Renderer_ArraySmarty($template, true);
    $rendererEditView->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
    $rendererEditView->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
    $formEditView->accept($rendererEditView);
    $template->assign('formEditView', $rendererEditView->toArray());

    /**
     * Form share view
     */
    $formShareView = new HTML_QuickFormCustom(
        'formShareView',
        'post',
        "?p=103",
        '',
        ['onSubmit' => 'submitShareView(); return false;']
    );

    /**
     * Users
     */
    $attrContacts = ['datasourceOrigin' => 'ajax', 'availableDatasetRoute' => './api/internal.php?object=centreon_configuration_contact&action=list', 'multiple' => true, 'allowClear' => true, 'defaultDataset' => []];
    $formShareView->addElement(
        'select2',
        'unlocked_user_id',
        _("Unlocked users"),
        [],
        $attrContacts
    );
    $formShareView->addElement(
        'select2',
        'locked_user_id',
        _("Locked users"),
        [],
        $attrContacts
    );

    /**
     * User groups
     */
    $attrContactgroups = ['datasourceOrigin' => 'ajax', 'availableDatasetRoute' => './api/internal.php?object=centreon_configuration_contactgroup&action=list', 'multiple' => true, 'allowClear' => true, 'defaultDataset' => []];
    $formShareView->addElement(
        'select2',
        'unlocked_usergroup_id',
        _("Unlocked user groups"),
        [],
        $attrContactgroups
    );
    $formShareView->addElement(
        'select2',
        'locked_usergroup_id',
        _("Locked user groups"),
        [],
        $attrContactgroups
    );

    /*
     * Widgets
     */
    $attrWidgets = ['datasourceOrigin' => 'ajax', 'multiple' => false, 'availableDatasetRoute' => './api/internal.php?object=centreon_administration_widget&action=listInstalled', 'allowClear' => false];

    /**
     * Submit button
     */
    $formShareView->addElement('submit', 'submit', _("Share"), ["class" => "btc bt_info"]);
    $formShareView->addElement('reset', 'reset', _("Reset"), ["class" => "btc bt_default"]);
    $formShareView->addElement('hidden', 'action');
    $formShareView->setDefaults(['action' => 'share']);
    $formShareView->addElement('hidden', 'custom_view_id');
    $rendererShareView = new HTML_QuickForm_Renderer_ArraySmarty($template, true);
    $rendererShareView->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
    $rendererShareView->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
    $formShareView->accept($rendererShareView);
    $template->assign('formShareView', $rendererShareView->toArray());

    /**
     * Form add widget
     */
    $widgetObj = new CentreonWidget($centreon, $db);
    $formAddWidget = new HTML_QuickFormCustom(
        'formAddWidget',
        'post',
        "?p=103",
        '',
        ['onSubmit' => 'submitAddWidget(); return false;']
    );

    /**
     * Name
     */
    $formAddWidget->addElement('text', 'widget_title', _("Title"), $attrsText);
    $formAddWidget->addElement('select2', 'widget_model_id', _("Widget"), [], $attrWidgets);

    /**
     * Submit button
     */
    $formAddWidget->addElement('submit', 'submit', _("Submit"), ["class" => "btc bt_success"]);
    $formAddWidget->addElement('reset', 'reset', _("Reset"), ["class" => "btc bt_default"]);
    $formAddWidget->addElement('hidden', 'action');
    $formAddWidget->addElement('hidden', 'custom_view_id');
    $formAddWidget->setDefaults(['action' => 'addWidget']);

    /**
     * Renderer
     */
    $rendererAddWidget = new HTML_QuickForm_Renderer_ArraySmarty($template, true);
    $rendererAddWidget->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
    $rendererAddWidget->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
    $formAddWidget->accept($rendererAddWidget);
    $template->assign('formAddWidget', $rendererAddWidget->toArray());
    $template->assign('rotationTimer', $rotationTimer);
    $template->assign(
        'editModeIcon',
        returnSvg(
            "www/img/icons/edit_mode.svg",
            "var(--icons-fill-color)",
            20,
            20
        )
    );
    $template->assign(
        'noEditModeIcon',
        returnSvg(
            "www/img/icons/no_edit_mode.svg",
            "var(--icons-fill-color)",
            20,
            20
        )
    );
    $template->assign(
        'addIcon',
        returnSvg("www/img/icons/add.svg", "var(--button-icons-fill-color)", 14, 16)
    );
    $template->assign(
        'deleteIcon',
        returnSvg("www/img/icons/trash.svg", "var(--button-icons-fill-color)", 14, 16)
    );
    $template->assign(
        'editIcon',
        returnSvg("www/img/icons/edit.svg", "var(--button-icons-fill-color)", 14, 14)
    );
    $template->assign(
        'returnIcon',
        returnSvg("www/img/icons/return.svg", "var(--button-icons-fill-color)", 14, 14)
    );
    $template->assign(
        'folderIcon',
        returnSvg("www/img/icons/folder.svg", "var(--button-icons-fill-color)", 14, 14)
    );
    $template->assign(
        'playIcon',
        returnSvg("www/img/icons/play.svg", "var(--button-icons-fill-color)", 14, 14)
    );
    $template->assign(
        'helpIcon',
        returnSvg("www/img/icons/question.svg", "var(--help-tool-tip-icon-fill-color)", 18, 18)
    );

    $template->display("index.ihtml");
} catch (CentreonCustomViewException $e) {
    echo $e->getMessage() . "<br/>";
}

// Initialize $modeEdit based on session variable
$modeEdit = 'false';
if (isset($_SESSION['customview_edit_mode'])) {
    $modeEdit = ($_SESSION['customview_edit_mode'] === 'true') ? 'true' : 'false';
}

$deprecationMessage = _('[Page deprecated] This page will be removed in the next major version. Please use the new page: ');
$resourcesStatusLabel = _('Dashboards');
$basePath = (Request::createFromGlobals())->getBasePath();
$redirectionUrl = $basePath . "/home/dashboards/library";
?>


<script type="text/javascript">
    var defaultShow = <?php echo $modeEdit; ?>;
    var deleteWdgtMessage =
        "<?php echo _("Deleting this widget might impact users with whom you are sharing this view. " .
            "Are you sure you want to do it?");?>";
    var deleteViewMessage =
        "<?php echo _("Deleting this view might impact other users. Are you sure you want to do it?");?>";
    var setDefaultMessage = "<?php echo _("Set this view as your default view?");?>";
    var wrenchSpan = '<span class="ui-icon ui-icon-wrench"></span>';
    var trashSpan = '<span class="ui-icon ui-icon-trash"></span>';

    function display_deprecated_banner() {
        const url = "<?php echo $redirectionUrl; ?>";
        const message = "<?php echo $deprecationMessage; ?>";
        const label = "<?php echo $resourcesStatusLabel; ?>";
        jQuery('.pathway').append(
            '<span style="color:#FF4500;padding-left:10px;font-weight:bold">' + message +
            '<a style="position:relative" href="' + url + '" isreact="isreact">' + label + '</a></span>'
        );
    }

    display_deprecated_banner();

    /**
     * Resize widget iframe
     */
    function iResize(ifrm, height) {
        if (height < 150) {
            height = 150;
        }
        jQuery("[name=" + ifrm + "]").height(height);
    }
</script>
