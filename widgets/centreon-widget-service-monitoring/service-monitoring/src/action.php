<?php
/**
 * Copyright 2005-2018 CENTREON
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
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonDB.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/class/centreonUtils.class.php';
require_once $centreon_path . 'www/class/centreonACL.class.php';
require_once $centreon_path . 'www/class/centreonHost.class.php';
require_once $centreon_path . 'www/class/centreonService.class.php';
require_once $centreon_path . 'www/class/centreonExternalCommand.class.php';

session_start();

try {
    if (!isset($_SESSION['centreon']) ||
        !isset($_REQUEST['cmd']) ||
        !isset($_REQUEST['selection'])
    ) {
        throw new Exception('Missing data');
    }
    $db = new CentreonDB();
    if (CentreonSession::checkSession(session_id(), $db) == 0) {
        throw new Exception('Invalid session');
    }
    $centreon = $_SESSION['centreon'];
    $oreon = $centreon;
    $cmd = $_REQUEST['cmd'];
    $wId = $_REQUEST['wid'];
    $selections = explode(",", $_REQUEST['selection']);
    $externalCmd = new CentreonExternalCommand($centreon);

    $hostObj = new CentreonHost($db);
    $svcObj = new CentreonService($db);
    $successMsg = _("External Command successfully submitted... Exiting window...");
    $result = 0;

    //retrieving the default timezone is the user didn't choose one
    $gmt = $centreon->user->getMyGMT();
    if (!$gmt) {
        $gmt = date_default_timezone_get();
    }

    if ($cmd == 72 || $cmd == 75 || $cmd == 70 || $cmd == 74) {
        $path = $centreon_path . "www/widgets/service-monitoring/src/";
        $template = new Smarty();
        $template = initSmartyTplForPopup($path, $template, "./", $centreon_path);
        $template->assign('stickyLabel', _("Sticky"));
        $template->assign('persistentLabel', _("Persistent"));
        $template->assign('authorLabel', _("Author"));
        $template->assign('notifyLabel', _("Notify"));
        $template->assign('commentLabel', _("Comment"));
        $template->assign('forceCheckLabel', _('Force active checks'));
        $template->assign('fixedLabel', _("Fixed"));
        $template->assign('durationLabel', _("Duration"));
        $template->assign('startLabel', _("Start"));
        $template->assign('endLabel', _("End"));
        $template->assign('selection', $_REQUEST['selection']);
        $template->assign('author', $centreon->user->alias);
        $template->assign('cmd', $cmd);
        if ($cmd == 72 || $cmd == 70) {
            $template->assign('ackHostSvcLabel', _("Acknowledge services of hosts"));
            if ($cmd == 72) {
                $title = _("Host Acknowledgement");
            } else {
                $title = _("Service Acknowledgement");
            }

            $template->assign('defaultMessage', sprintf(_('Acknowledged by %s'), $centreon->user->alias));

            /* Default ack options */
            $persistent_checked = '';
            if (isset($centreon->optGen['monitoring_ack_persistent']) && $centreon->optGen['monitoring_ack_persistent']) {
                $persistent_checked = 'checked';
            }
            $template->assign('persistent_checked', $persistent_checked);

            $sticky_checked = '';
            if (isset($centreon->optGen['monitoring_ack_sticky']) && $centreon->optGen['monitoring_ack_sticky']) {
                $sticky_checked = 'checked';
            }
            $template->assign('sticky_checked', $sticky_checked);

            $notify_checked = '';
            if (isset($centreon->optGen['monitoring_ack_notify']) && $centreon->optGen['monitoring_ack_notify']) {
                $notify_checked = 'checked';
            }
            $template->assign('notify_checked', $notify_checked);

            $process_service_checked = '';
            if (isset($centreon->optGen['monitoring_ack_svc']) && $centreon->optGen['monitoring_ack_svc']) {
                $process_service_checked = 'checked';
            }
            $template->assign('process_service_checked', $process_service_checked);

            $force_active_checked = '';
            if (isset($centreon->optGen['monitoring_ack_active_checks']) && $centreon->optGen['monitoring_ack_active_checks']) {
                $force_active_checked = 'checked';
            }
            $template->assign('force_active_checked', $force_active_checked);

            $template->assign('titleLabel', $title);
            $template->assign('submitLabel', _("Acknowledge"));
            $template->display('acknowledge.ihtml');
        } elseif ($cmd == 75 || $cmd == 74) {

            $hourStart = $centreon->CentreonGMT->getDate("H", time(), $gmt);
            $minuteStart = $centreon->CentreonGMT->getDate("i", time(), $gmt);

            $hourEnd = $centreon->CentreonGMT->getDate("H", time() + 7200, $gmt);
            $minuteEnd = $centreon->CentreonGMT->getDate("i", time() + 7200, $gmt);

            $template->assign('downtimeHostSvcLabel', _("Set downtime on services of hosts"));
            if ($cmd == 75) {
                $title = _("Host Downtime");
            } else {
                $title = _("Service Downtime");
            }

            /* Default downtime options */
            $process_service_checked = '';
            $fixed_checked = '';
            if (isset($centreon->optGen['monitoring_dwt_fixed']) && $centreon->optGen['monitoring_dwt_fixed']) {
                $fixed_checked = 'checked';
            }
            $template->assign('fixed_checked', $fixed_checked);

            if (isset($centreon->optGen['monitoring_dwt_svc']) && $centreon->optGen['monitoring_dwt_svc']) {
                $process_service_checked = 'checked';
            }
            $template->assign('process_service_checked', $process_service_checked);

            $template->assign('defaultMessage', sprintf(_('Downtime set by %s'), $centreon->user->alias));

            $template->assign('titleLabel', $title);
            $template->assign('submitLabel', _("Set Downtime"));
            $template->assign('defaultDuration', 2);
            $template->assign('daysLabel', _("days"));
            $template->assign('hoursLabel', _("hours"));
            $template->assign('minutesLabel', _("minutes"));
            $template->assign('defaultStart', $dateStart);
            $template->assign('defaultHourStart', $hourStart);
            $template->assign('defaultMinuteStart', $minuteStart);
            $template->assign('defaultEnd', $dateEnd);
            $template->assign('defaultHourEnd', $hourEnd);
            $template->assign('defaultMinuteEnd', $minuteEnd);
            $template->display('downtime.ihtml');
        }
    } else {
        $command = "";
        $isSvcCommand = false;
        switch ($cmd) {
            /* service: schedule check */
            case 3 :
                $command = "SCHEDULE_SVC_CHECK;%s;" . time();
                $isSvcCommand = true;
                break;
            /* service: schedule forced check */
            case 4 :
                $command = "SCHEDULE_FORCED_SVC_CHECK;%s;" . time();
                $isSvcCommand = true;
                break;
            /* service: remove ack */
            case 71 :
                $command = "REMOVE_SVC_ACKNOWLEDGEMENT;%s";
                $isSvcCommand = true;
                break;
            /* service: enable notif */
            case 80 :
                $command = "ENABLE_SVC_NOTIFICATIONS;%s";
                $isSvcCommand = true;
                break;
            /* service: enable notif */
            case 81 :
                $command = "DISABLE_SVC_NOTIFICATIONS;%s";
                $isSvcCommand = true;
                break;
            /* service: enable check */
            case 90 :
                $command = "ENABLE_SVC_CHECK;%s";
                $isSvcCommand = true;
                break;
            /* service: disable check */
            case 91 :
                $command = "DISABLE_SVC_CHECK;%s";
                $isSvcCommand = true;
                break;
            /* host: remove ack */
            case 73 :
                $command = "REMOVE_HOST_ACKNOWLEDGEMENT;%s";
                break;
            /* host: enable notif */
            case 82 :
                $command = "ENABLE_HOST_NOTIFICATIONS;%s";
                break;
            /* host: disable notif */
            case 83 :
                $command = "DISABLE_HOST_NOTIFICATIONS;%s";
                break;
            /* host: enable check */
            case 92 :
                $command = "ENABLE_HOST_CHECK;%s";
                break;
            /* host: disable check */
            case 93 :
                $command = "DISABLE_HOST_CHECK;%s";
                break;
            default :
                throw new Exception('Unknown command');
                break;
        }
        if ($command != "") {
            $externalCommandMethod = 'set_process_command';
            if (method_exists($externalCmd, 'setProcessCommand')) {
                $externalCommandMethod = 'setProcessCommand';
            }
            $hostArray = array();
            foreach ($selections as $selection) {
                $tmp = explode(";", $selection);
                if (count($tmp) != 2) {
                    throw new Exception('Incorrect id format');
                }
                $hostId = $tmp[0];
                $svcId = $tmp[1];
                if ($hostId != 0 && $svcId != 0) {
                    $hostname = $hostObj->getHostName($hostId);
                    $svcDesc = $svcObj->getServiceDesc($svcId);
                    if ($isSvcCommand === true) {
                        $cmdParam = $hostname . ";" . $svcDesc;
                    } else {
                        $cmdParam = $hostname;
                    }
                    $externalCmd->$externalCommandMethod(sprintf(
                        $command,
                        $cmdParam),
                        $hostObj->getHostPollerId($hostId)
                    );
                }
            }
            $externalCmd->write();
        }
        $result = 1;
    }
} catch (Exception $e) {
    echo $e->getMessage() . "<br/>";
}

?>
<div id='result'></div>
<script type='text/javascript'>
    var result = <?php echo $result; ?>;
    var successMsg = "<?php echo $successMsg; ?>";
    var wId = "<?php echo $wId; ?>";
    var widgetName = 'widget_' + viewId + '_' + wId;

    jQuery(function () {
        if (result) {
            jQuery("#result").html(successMsg);
            setTimeout('closeBox()', 2000);
            setTimeout('reloadFrame(widgetName)', 2500);
        }
        jQuery("#submit").click(function () {
            sendCmd();
            setTimeout('reloadFrame(widgetName)', 2500);
        });
        jQuery("#submit").button();
        toggleDurationField();
        jQuery("[name=fixed]").click(function () {
            toggleDurationField();
        });

        //initializing datepicker and timepicker
        initDatepicker("datepicker", "yy/mm/dd", "0");
        jQuery("#start_time,#end_time").timepicker();
    });

    function closeBox()
    {
        jQuery('#widgetPopin').centreonPopin('close');
    }

    function reloadFrame(widgetName) {
        document.getElementsByName(widgetName)[0].contentWindow.location.reload(true);
    }

    function sendCmd()
    {
        fieldResult = true;
        if (jQuery("#comment") && !jQuery("#comment").val()) {
            fieldResult = false;
        }
        if (fieldResult == false) {
            jQuery("#result").html("<font color=red><b>Please fill all mandatory fields.</b></font>");
            return false;
        }
        jQuery.ajax({
            type: "POST",
            url: "./widgets/service-monitoring/src/sendCmd.php",
            data: jQuery("#Form").serialize(),
            success: function () {
                jQuery("#result").html(successMsg);
                setTimeout('closeBox()', 2000);
            }
        });
    }

    function toggleDurationField()
    {
        if (jQuery("[name=fixed]").is(':checked')) {
            jQuery("[name=dayduration]").attr('disabled', true);
            jQuery("[name=hourduration]").attr('disabled', true);
            jQuery("[name=minuteduration]").attr('disabled', true);
        } else {
            jQuery("[name=dayduration]").removeAttr('disabled');
            jQuery("[name=hourduration]").removeAttr('disabled');
            jQuery("[name=minuteduration]").removeAttr('disabled');
        }
    }
</script>
