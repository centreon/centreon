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
 * As a special exception, the copyright holders of this program give CENTREON
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of CENTREON choice, provided that
 * CENTREON also meet, for each linked independent module, the terms  and conditions
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
    print "Session Errors";
    exit;
}

$path = $centreon_path . "www/widgets/service-monitoring/src/";
$template = new Smarty();
$template = initSmartyTplForPopup($path, $template, "./", $centreon_path);

$centreon = $_SESSION['centreon'];
$widgetId = filter_input(INPUT_POST, 'widgetId', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
$db = $dependencyInjector['configuration_db'];
$widgetObj = new CentreonWidget($centreon, $db);
$preferences = $widgetObj->getWidgetPreferences($widgetId);

$admin = $centreon->user->admin;
$canDoAction = false;
if ($admin) {
    $canDoAction = true;
}
$actions  = "<option value='0'>-- "._("More actions")." -- </option>";
if ($canDoAction || $centreon->user->access->checkAction("service_schedule_check")) {
    $actions .= "<option value='3'>"._("Service: Schedule Immediate Check")."</option>";
}
if ($canDoAction || $centreon->user->access->checkAction("service_schedule_forced_check")) {
    $actions .= "<option value='4'>"._("Service: Schedule Immediate Forced Check")."</option>";
}
if ($canDoAction || $centreon->user->access->checkAction("service_acknowledgement")) {
    $actions .= "<option value='70'>"._("Service: Acknowledge")."</option>";
}
if ($canDoAction || $centreon->user->access->checkAction("service_disacknowledgement")) {
    $actions .= "<option value='71'>"._("Service: Remove Acknowledgement")."</option>";
}
if ($canDoAction || $centreon->user->access->checkAction("service_schedule_downtime")) {
    $actions .= "<option value='74'>"._("Service: Set Downtime")."</option>";
}
if ($canDoAction || $centreon->user->access->checkAction("service_notifications")) {
    $actions .= "<option value='80'>"._("Service: Enable Notification")."</option>";
    $actions .= "<option value='81'>"._("Service: Disable Notification")."</option>";
}
if ($canDoAction || $centreon->user->access->checkAction("service_checks")) {
    $actions .= "<option value='90'>"._("Service: Enable Check")."</option>";
    $actions .= "<option value='91'>"._("Service: Disable Check")."</option>";
}
if ($canDoAction || $centreon->user->access->checkAction("host_acknowledgement")) {
    $actions .= "<option value='72'>"._("Host: Acknowledge")."</option>";
}
if ($canDoAction || $centreon->user->access->checkAction("host_disacknowledgement")) {
    $actions .= "<option value='73'>"._("Host: Remove Acknowledgement")."</option>";
}
if ($canDoAction || $centreon->user->access->checkAction("host_schedule_downtime")) {
    $actions .= "<option value='75'>"._("Host: Set Downtime")."</option>";
}
if ($canDoAction || $centreon->user->access->checkAction("host_notifications")) {
    $actions .= "<option value='82'>"._("Host: Enable Host Notification")."</option>";
    $actions .= "<option value='83'>"._("Host: Disable Host Notification")."</option>";
}
if ($canDoAction || $centreon->user->access->checkAction("host_checks")) {
    $actions .= "<option value='92'>"._("Host: Enable Host Check")."</option>";
    $actions .= "<option value='93'>"._("Host: Disable Host Check")."</option>";
}

$template->assign("widgetId", $widgetId);
$template->assign("actions", $actions);
$template->display('toolbar.ihtml');

?>
<script type="text/javascript" src="../../include/common/javascript/centreon/popin.js"></script>
<script type='text/javascript'>
var tab = new Array();
var actions = "<?php echo $actions;?>";
var wid = "<?php echo $widgetId;?>";


jQuery( function() {
    jQuery(".toolbar").change( function() {

        if (jQuery(this).val() != 0) {
            var checkValues = jQuery("input:checked")
                .map( function() {
                      var tmp = jQuery(this).attr('id').split("_");
                      return tmp[1];
                })
                .get().join(",");

            if (checkValues != '') {
                var url = "./widgets/service-monitoring/src/action.php?selection=" + checkValues +
                    "&cmd=" + jQuery(this).val() + "&wid=" + wid;
                parent.jQuery('#widgetPopin').parent().remove();
                var popin = parent.jQuery('<div id="widgetPopin">');

                popin.centreonPopin({
                  open:true,
                  url:url
                  });

            } else {
                alert("<?php echo _('Please select one or more items'); ?>");
            }

            jQuery(this).val(0);
        }
    });
});
</script>
