<?php

/*
 * Copyright 2005-2020 Centreon
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
 */

require_once "../../require.php";
require_once $centreon_path . 'bootstrap.php';
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/class/centreonDuration.class.php';
require_once $centreon_path . 'www/class/centreonUtils.class.php';
require_once $centreon_path . 'www/class/centreonACL.class.php';

session_start();
if (!isset($_SESSION['centreon']) || !isset($_POST['widgetId'])) {
    exit;
}

$path = $centreon_path . "www/widgets/host-monitoring/src/";
$template = new Smarty();
$template = initSmartyTplForPopup($path, $template, "./", $centreon_path);

$centreon = $_SESSION['centreon'];
$widgetId = filter_input(INPUT_POST, 'widgetId', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
$db = new CentreonDB();
$widgetObj = new CentreonWidget($centreon, $db);
$preferences = $widgetObj->getWidgetPreferences($widgetId);

$admin = $centreon->user->admin;
$canDoAction = false;
if ($admin) {
    $canDoAction = true;
}
$actions = "<option value='0'>-- " . _("More actions") . " -- </option>";
if ($canDoAction || $centreon->user->access->checkAction("host_acknowledgement")) {
    $actions .= "<option value='72'>" . _("Acknowledge") . "</option>";
}
if ($canDoAction || $centreon->user->access->checkAction("host_disacknowledgement")) {
    $actions .= "<option value='73'>" . _("Remove Acknowledgement") . "</option>";
}
if ($canDoAction || $centreon->user->access->checkAction("host_schedule_downtime")) {
    $actions .= "<option value='75'>" . _("Set Downtime") . "</option>";
}
if ($canDoAction || $centreon->user->access->checkAction("host_notifications")) {
    $actions .= "<option value='82'>" . _("Enable Host Notification") . "</option>";
    $actions .= "<option value='83'>" . _("Disable Host Notification") . "</option>";
}
if ($canDoAction || $centreon->user->access->checkAction("host_checks")) {
    $actions .= "<option value='92'>" . _("Enable Host Check") . "</option>";
    $actions .= "<option value='93'>" . _("Disable Host Check") . "</option>";
}

$template->assign("widgetId", $widgetId);
$template->assign("actions", $actions);
$template->display('toolbar.ihtml');

?>
<script type="text/javascript" src="../../include/common/javascript/centreon/popin.js"></script>
<script type='text/javascript'>

    var tab = new Array();
    var actions = "<?php echo $actions;?>";
    var widget_id = "<?php echo $widgetId; ?>";

    jQuery(function () {
        jQuery(".toolbar").change(function () {

            if (jQuery(this).val() != 0) {
                var checkValues = jQuery("input:checked")
                    .map(function () {
                        var tmp = jQuery(this).attr('id').split("_");
                        return tmp[1];
                    })
                    .get().join(",");

                if (checkValues != '') {
                    var url = "./widgets/host-monitoring/src/action.php?widgetId=" + widgetId +
                        "&selection=" + checkValues + "&cmd=" + jQuery(this).val();
                    parent.jQuery('#WidgetDowntime').parent().remove();
                    var popin = parent.jQuery('<div id="WidgetDowntime">');

                    popin.centreonPopin({
                        open: true,
                        url: url,
                        onClose: () => {
                            checkValues.split(',').forEach((value) => {
                                localStorage.removeItem('w_hm_selection_' + value);
                                jQuery('#selection_' + value).prop('checked', false);
                            });
                        }
                    });
                } else {
                    alert("<?php echo _('Please select one or more items'); ?>");
                }

                jQuery(this).val(0);
            }
        });
    });
</script>
