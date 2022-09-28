<?php
/*
 * Copyright 2015-2022 Centreon (http://www.centreon.com/)
 *
 * Centreon is a full-fledged industry-strength solution that meets
 * the needs in IT infrastructure and application monitoring for
 * service performance.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,*
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

require_once "../../require.php";
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonDB.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/class/centreonDuration.class.php';
require_once $centreon_path . 'www/class/centreonUtils.class.php';
require_once $centreon_path . 'www/class/centreonACL.class.php';

session_start();
if (!isset($_SESSION['centreon']) || !isset($_POST['widgetId'])) {
    exit;
}

$smartyDir = __DIR__ . '/../../../../vendor/smarty/smarty/';
require_once $smartyDir . 'libs/Smarty.class.php';

$path = $centreon_path . "www/widgets/open-tickets/src/";
$template = new Smarty();
$template = initSmartyTplForPopup($path . 'templates/', $template, "./", $centreon_path);

$centreon = $_SESSION['centreon'];
$widgetId = $_POST['widgetId'];
$db = new CentreonDB();
$widgetObj = new CentreonWidget($centreon, $db);
$preferences = $widgetObj->getWidgetPreferences($widgetId);

$pearDB = new CentreonDB();
$admin = $centreon->user->admin;
$canDoAction = false;
if ($admin) {
    $canDoAction = true;
}

$toolbar = '';
if ($preferences['toolbar_buttons']) {
    if (!isset($preferences['opened_tickets']) || $preferences['opened_tickets'] == 0) {
        if ($preferences['action_open_hosts']) {
            $toolbar .= "<label id='buttontoolbar_4' style='font-size: 13px; font-weight: bold; cursor:pointer;' "
                . "for='host-ticket'>Host <input type='image' title='"
                . _("Host: Open ticket") . "' alt='" . _("Host: Open ticket") . "' src='"
                . $centreon->optGen['oreon_web_path']
                . "/modules/centreon-open-tickets/images/open-ticket.svg' name='host-ticket' "
                . "style='border: none; width: 24px; height: 24px; vertical-align: middle;'/> </label> | ";
        }
        if ($preferences['action_open_services']) {
            $toolbar .= "<label id='buttontoolbar_3' style='font-size: 13px; font-weight: bold; cursor:pointer;'  "
                . "for='service-ticket'> Service <input type='image' title='" . _("Service: Open ticket") . "' alt='"
                . _("Service: Open ticket") . "' src='" . $centreon->optGen['oreon_web_path']
                . "/modules/centreon-open-tickets/images/open-ticket.svg' name='service-ticket' "
                . "style='border: none; width: 24px; height: 24px; vertical-align: middle;' /> </label> | ";
        }
        if (
            $preferences['action_ack']
            && ($canDoAction || $centreon->user->access->checkAction("service_acknowledgement"))
        ) {
            $toolbar .= "<label id='buttontoolbar_70' style='font-size: 13px; font-weight: bold; cursor:pointer;'' "
                . "for='ack-ticket'>Acknowledge <input type='image' title='" . _("Service: Acknowledge") . "' alt='"
                . _("Service: Acknowledge") . "' src='" . $centreon->optGen['oreon_web_path']
                . "/modules/centreon-open-tickets/images/acknowledge.png' name='ack-ticket' "
                . "style='border: none; height: 22px; vertical-align: middle;' /> </label> | ";
        }
        if (
            $preferences['action_service_forced_check']
            && ($canDoAction || $centreon->user->access->checkAction("service_schedule_forced_check"))
        ) {
            $toolbar .= "<label id='buttontoolbar_80' style='font-size: 13px; font-weight: bold; cursor:pointer;'' "
                . "for='schedule-service-forced-check-ticket'>Service: Schedule forced check <input type='image' "
                . "title='" . _("Service: Schedule Forced Check") . "' alt='"
                . _("Service: Schedule Forced Check") . "' src='" . $centreon->optGen['oreon_web_path']
                . "/modules/centreon-open-tickets/images/schedule_forced_check.png' "
                . "name='schedule-service-forced-check-ticket' "
                . "style='border: none; height: 22px; vertical-align: middle;' /> </label> | ";
        }
        if (
            $preferences['action_service_check']
            && ($canDoAction || $centreon->user->access->checkAction("service_schedule_check"))
        ) {
            $toolbar .= "<label id='buttontoolbar_81' style='font-size: 13px; font-weight: bold; cursor:pointer;'' "
                . "for='schedule-sevice-check-ticket'>Service: Schedule check <input type='image' title='"
                . _("Service: Schedule Check") . "' alt='"
                . _("Service: Schedule Check") . "' src='" . $centreon->optGen['oreon_web_path']
                . "/modules/centreon-open-tickets/images/schedule_check.png' name='schedule-service-check-ticket' "
                . "style='border: none; height: 22px; vertical-align: middle;' /> </label> | ";
        }
        if (
            $preferences['action_host_forced_check']
            && ($canDoAction || $centreon->user->access->checkAction("host_schedule_forced_check"))
        ) {
            $toolbar .= "<label id='buttontoolbar_82' style='font-size: 13px; font-weight: bold; cursor:pointer;'' "
                . "for='host-service-forced-check-ticket'>Host: Schedule forced check <input type='image' title='"
                . _("Host: Schedule Forced Check") . "' alt='"
                . _("Host: Schedule Forced Check") . "' src='" . $centreon->optGen['oreon_web_path']
                . "/modules/centreon-open-tickets/images/schedule_forced_check.png' "
                . "name='schedule-host-forced-check-ticket' "
                . "style='border: none; height: 22px; vertical-align: middle;' /> </label> | ";
        }
        if (
            $preferences['action_host_check']
            && ($canDoAction || $centreon->user->access->checkAction("host_schedule_check"))
        ) {
            $toolbar .= "<label id='buttontoolbar_83' style='font-size: 13px; font-weight: bold; cursor:pointer;'' "
                . "for='schedule-host-check-ticket'>Host: Schedule check <input type='image' title='"
                . _("Host: Schedule Check") . "' alt='"
                . _("Host: Schedule Check") . "' src='" . $centreon->optGen['oreon_web_path']
                . "/modules/centreon-open-tickets/images/schedule_check.png' name='schedule-host-check-ticket' "
                . "style='border: none; height: 22px; vertical-align: middle;' /> </label> |";
        }
    } else {
        $toolbar .= "<input type='image' title='" . _("Close Tickets") . "' alt='" . _("Close Tickets")
            . "' src='" . $centreon->optGen['oreon_web_path']
            . "/modules/centreon-open-tickets/images/close-ticket.svg' id='buttontoolbar_10' "
            . "style='cursor:pointer; border: none;width: 24px; height: 24px;' />";
    }
} else {
    $toolbar .= "<select class='toolbar'>";
    $toolbar .= "<option value='0'>-- " . _("More actions") . " -- </option>";

    if (!isset($preferences['opened_tickets']) || $preferences['opened_tickets'] == 0) {
        if ($preferences['action_open_hosts']) {
            $toolbar .= "<option value='4'>" . _("Host: Open ticket") . "</option>";
        }
        if ($preferences['action_open_services']) {
            $toolbar .= "<option value='3'>" . _("Service: Open ticket") . "</option>";
        }
        if (
            $preferences['action_ack']
            && ($canDoAction || $centreon->user->access->checkAction("service_acknowledgement"))
        ) {
            $toolbar .= "<option value='70'>" . _("Service: Acknowledge") . "</option>";
        }
        if (
            $preferences['action_host_forced_check']
            && ($canDoAction || $centreon->user->access->checkAction("host_schedule_forced_check"))
        ) {
            $toolbar .= "<option value='82'>" . _("Host: Schedule Forced Check") . "</option>";
        }
        if (
            $preferences['action_host_check']
            && ($canDoAction || $centreon->user->access->checkAction("host_schedule_check"))
        ) {
            $toolbar .= "<option value='83'>" . _("Host: Schedule Check") . "</option>";
        }
        if (
            $preferences['action_service_forced_check']
            && ($canDoAction || $centreon->user->access->checkAction("service_schedule_forced_check"))
        ) {
            $toolbar .= "<option value='80'>" . _("Service: Schedule Forced Check") . "</option>";
        }
        if (
            $preferences['action_service_check']
            && ($canDoAction || $centreon->user->access->checkAction("service_schedule_check"))
        ) {
            $toolbar .= "<option value='81'>" . _("Service: Schedule Check") . "</option>";
        }
    } else {
        $toolbar .= "<option value='10'>" . _("Close Tickets") . "</option>";
    }
    $toolbar .= "</select>";
}

$template->assign("widgetId", $widgetId);
$template->display('toolbar.ihtml');

?>

<script type="text/javascript" src="../../include/common/javascript/centreon/popin.js"></script>

<script type='text/javascript'>
var tab = new Array();
var toolbar = "<?php echo $toolbar;?>";
var widget_id = "<?php echo $widgetId; ?>";

$(function() {
    $("#toolbar_container").html(toolbar);
    $(".toolbar").change(function() {
        if (jQuery(this).val() != 0) {
            var checkValues = $("input:checked").map(function() {
                var tmp = $(this).attr('id').split("_");
                return tmp[1];
            }).get().join(",");

            if (checkValues != '') {
                var url = "./widgets/open-tickets/src/action.php?widgetId="
                    + widget_id + "&selection=" + checkValues + "&cmd=" + jQuery(this).val();
                // We delete the old one 
                // (not really clean. Should be managed by popin itself. Like with a destroy parameters)
                parent.jQuery('#OTWidgetPopin').parent().remove();
                var popin = parent.jQuery('<div id="OTWidgetPopin">');
                popin.centreonPopin({open:true,url:url});
            } else {
                alert("<?php echo _('Please select one or more items'); ?>");
                return false;
            }
            $(".toolbar").val(0);
        }
    });

    $("[id^=buttontoolbar_]").click(function() {
        var checkValues = $("input:checked").map(function() {
            var tmp = $(this).attr('id').split("_");
            return tmp[1];
        }).get().join(",");

        if (checkValues != '') {
            var tmp = $(this).attr('id').split("_");
            var url = "./widgets/open-tickets/src/action.php?widgetId=" 
                + widget_id + "&selection=" + checkValues + "&cmd=" + tmp[1];
            // We delete the old one 
            // (not really clean. Should be managed by popin itself. Like with a destroy parameters)
            parent.jQuery('#OTWidgetPopin').parent().remove();
            var popin = parent.jQuery('<div id="OTWidgetPopin">');
            popin.centreonPopin({open:true,url:url});
        } else {
            alert("<?php echo _('Please select one or more items'); ?>");
        }
    });
});
</script>