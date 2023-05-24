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
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonDB.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/class/centreonUtils.class.php';
require_once $centreon_path . 'www/class/centreonACL.class.php';
require_once $centreon_path . 'www/class/centreonHost.class.php';
require_once $centreon_path . 'www/class/centreonExternalCommand.class.php';

session_start();

try {
    if (
        !isset($_SESSION['centreon'])
        || !isset($_REQUEST['cmd'])
        || !isset($_REQUEST['selection'])
    ) {
        throw new Exception('Missing data');
    }
    $db = new CentreonDB();
    if (CentreonSession::checkSession(session_id(), $db) == 0) {
        throw new Exception('Invalid session');
    }
    $centreon = $_SESSION['centreon'];
    $oreon = $centreon;
    $cmd = filter_input(INPUT_GET, 'cmd', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);

    $selection = '';
    $hosts = explode(",", $_GET['selection']);
    foreach ($hosts as $host) {
        $selection .= (filter_var($host, FILTER_VALIDATE_INT) ?: 0) . ',';
    }
    $selection = rtrim($selection, ',');

    $externalCmd = new CentreonExternalCommand($centreon);
    $hostObj = new CentreonHost($db);
    $successMsg = _("External Command successfully submitted... Exiting window...");
    $result = 0;

    $defaultDuration = 7200;
    $defaultScale = 's';
    if (!empty($centreon->optGen['monitoring_dwt_duration'])) {
        $defaultDuration = $centreon->optGen['monitoring_dwt_duration'];
        if (!empty($centreon->optGen['monitoring_dwt_duration_scale'])) {
            $defaultScale = $centreon->optGen['monitoring_dwt_duration_scale'];
        }
    }

    if ($cmd == 72 || $cmd == 75) {
        $path = $centreon_path . "www/widgets/host-monitoring/src/";
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
        $template->assign('hosts', $selection);
        $template->assign('author', $centreon->user->name);
        if ($cmd == 72) {
            $template->assign('ackHostSvcLabel', _("Acknowledge services of hosts"));
            $template->assign('defaultMessage', sprintf(_('Acknowledged by %s'), $centreon->user->alias));
            $template->assign('titleLabel', _("Host Acknowledgement"));
            $template->assign('submitLabel', _("Acknowledge"));

            /* default ack options */
            $persistent_checked = '';
            if (!empty($centreon->optGen['monitoring_ack_persistent'])) {
                $persistent_checked = 'checked';
            }
            $template->assign('persistent_checked', $persistent_checked);

            $sticky_checked = '';
            if (!empty($centreon->optGen['monitoring_ack_sticky'])) {
                $sticky_checked = 'checked';
            }
            $template->assign('sticky_checked', $sticky_checked);

            $notify_checked = '';
            if (!empty($centreon->optGen['monitoring_ack_notify'])) {
                $notify_checked = 'checked';
            }
            $template->assign('notify_checked', $notify_checked);

            $process_service_checked = '';
            if (!empty($centreon->optGen['monitoring_ack_svc'])) {
                $process_service_checked = 'checked';
            }
            $template->assign('process_service_checked', $process_service_checked);

            $force_active_checked = '';
            if (!empty($centreon->optGen['monitoring_ack_active_checks'])) {
                $force_active_checked = 'checked';
            }
            $template->assign('force_active_checked', $force_active_checked);

            $template->display('acknowledge.ihtml');
        } elseif ($cmd == 75) {
            $template->assign('downtimeHostSvcLabel', _("Set downtime on services of hosts"));
            $template->assign('defaultMessage', sprintf(_('Downtime set by %s'), $centreon->user->alias));
            $template->assign('titleLabel', _("Host Downtime"));
            $template->assign('submitLabel', _("Set Downtime"));
            $template->assign('defaultDuration', $defaultDuration);
            $template->assign('sDurationLabel', _("seconds"));
            $template->assign('mDurationLabel', _("minutes"));
            $template->assign('hDurationLabel', _("hours"));
            $template->assign('dDurationLabel', _("days"));
            $template->assign($defaultScale . 'DefaultScale', 'selected');

            /* default downtime options */
            $fixed_checked = '';
            if (!empty($centreon->optGen['monitoring_dwt_fixed'])) {
                $fixed_checked = 'checked';
            }
            $template->assign('fixed_checked', $fixed_checked);

            $process_service_checked = '';
            if (!empty($centreon->optGen['monitoring_dwt_svc'])) {
                $process_service_checked = 'checked';
            }
            $template->assign('process_service_checked', $process_service_checked);
            $template->display('downtime.ihtml');
        }
    } else {
        $command = '';
        switch ($cmd) {
            /* remove ack */
            case 73:
                $command = "REMOVE_HOST_ACKNOWLEDGEMENT;%s";
                break;
            /* enable notif */
            case 82:
                $command = "ENABLE_HOST_NOTIFICATIONS;%s";
                break;
            /* disable notif */
            case 83:
                $command = "DISABLE_HOST_NOTIFICATIONS;%s";
                break;
            /* enable check */
            case 92:
                $command = "ENABLE_HOST_CHECK;%s";
                break;
            /* disable check */
            case 93:
                $command = "DISABLE_HOST_CHECK;%s";
                break;
            default:
                throw new Exception('Unknown command');
                break;
        }
        if ($command != "") {
            $externalCommandMethod = 'set_process_command';
            if (method_exists($externalCmd, 'setProcessCommand')) {
                $externalCommandMethod = 'setProcessCommand';
            }
            foreach ($hosts as $hostId) {
                $hostId = filter_var($hostId, FILTER_VALIDATE_INT) ?: 0;
                if ($hostId !== 0) {
                    $externalCmd->$externalCommandMethod(
                        sprintf($command, $hostObj->getHostName($hostId)),
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
    var result = <?php echo $result;?>;
    var successMsg = "<?php echo $successMsg;?>";

    jQuery(function () {
        if (result) {
            jQuery("#result").html(successMsg);
            setTimeout('closeBox()', 2000);
        }
        jQuery("#submit").click(function () {
            sendCmd();
        });
        jQuery("#submit").button();
        toggleDurationField();
        jQuery("[name=fixed]").click(function () {
            toggleDurationField();
        });

        //initializing datepicker and timepicker
        jQuery(".timepicker").each(function () {
            if (!$(this).val()) {
                $(this).val(moment().tz(localStorage.getItem('realTimezone')
                    ? localStorage.getItem('realTimezone')
                    : moment.tz.guess()).format("HH:mm")
                );
            }
        });
        jQuery("#start_time, #end_time").timepicker();
        initDatepicker();
        turnOnEvents();
        updateDateAndTime();
    });

    function closeBox() {
        jQuery('#WidgetDowntime').centreonPopin('close');
    }

    function sendCmd() {
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
            url: "./widgets/host-monitoring/src/sendCmd.php",
            data: jQuery("#Form").serialize(),
            success: function () {
                jQuery("#result").html(successMsg);
                setTimeout('closeBox()', 2000);
            }
        });
    }

    function toggleDurationField() {
        if (jQuery("[name=fixed]").is(':checked')) {
            jQuery("[name=duration]").attr('disabled', true);
            jQuery("[name=duration_scale]").attr('disabled', true);
        } else {
            jQuery("[name=duration]").removeAttr('disabled');
            jQuery("[name=duration_scale]").removeAttr('disabled');
        }
    }
</script>
