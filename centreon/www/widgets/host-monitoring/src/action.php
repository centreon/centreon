<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

require_once '../../require.php';
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonDB.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/class/centreonUtils.class.php';
require_once $centreon_path . 'www/class/centreonHost.class.php';
require_once $centreon_path . 'www/class/centreonExternalCommand.class.php';

session_start();

try {
    if (
        ! isset($_SESSION['centreon'])
        || ! isset($_REQUEST['cmd'])
        || ! isset($_REQUEST['selection'])
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
    $hosts = explode(',', $_GET['selection']);
    foreach ($hosts as $host) {
        $selection .= (filter_var($host, FILTER_VALIDATE_INT) ?: 0) . ',';
    }
    $selection = rtrim($selection, ',');

    $externalCmd = new CentreonExternalCommand($centreon);
    $hostObj = new CentreonHost($db);
    $successMsg = _('External Command successfully submitted... Exiting window...');
    $result = 0;

    $defaultDuration = 7200;
    $defaultScale = 's';
    if (! empty($centreon->optGen['monitoring_dwt_duration'])) {
        $defaultDuration = $centreon->optGen['monitoring_dwt_duration'];
        if (! empty($centreon->optGen['monitoring_dwt_duration_scale'])) {
            $defaultScale = $centreon->optGen['monitoring_dwt_duration_scale'];
        }
    }

    if ($cmd == 72 || $cmd == 75) {
        // Smarty template initialization
        $path = $centreon_path . 'www/widgets/host-monitoring/src/';
        $template = SmartyBC::createSmartyTemplate($path, './');

        $template->assign('stickyLabel', _('Sticky'));
        $template->assign('persistentLabel', _('Persistent'));
        $template->assign('authorLabel', _('Author'));
        $template->assign('notifyLabel', _('Notify'));
        $template->assign('commentLabel', _('Comment'));
        $template->assign('forceCheckLabel', _('Force active checks'));
        $template->assign('fixedLabel', _('Fixed'));
        $template->assign('durationLabel', _('Duration'));
        $template->assign('startLabel', _('Start'));
        $template->assign('endLabel', _('End'));
        $template->assign('hosts', $selection);
        $template->assign('author', $centreon->user->name);
        if ($cmd == 72) {
            $template->assign('ackHostSvcLabel', _('Acknowledge services of hosts'));
            $template->assign('defaultMessage', sprintf(_('Acknowledged by %s'), $centreon->user->alias));
            $template->assign('titleLabel', _('Host Acknowledgement'));
            $template->assign('submitLabel', _('Acknowledge'));

            // default ack options
            $persistent_checked = '';
            if (! empty($centreon->optGen['monitoring_ack_persistent'])) {
                $persistent_checked = 'checked';
            }
            $template->assign('persistent_checked', $persistent_checked);

            $sticky_checked = '';
            if (! empty($centreon->optGen['monitoring_ack_sticky'])) {
                $sticky_checked = 'checked';
            }
            $template->assign('sticky_checked', $sticky_checked);

            $notify_checked = '';
            if (! empty($centreon->optGen['monitoring_ack_notify'])) {
                $notify_checked = 'checked';
            }
            $template->assign('notify_checked', $notify_checked);

            $process_service_checked = '';
            if (! empty($centreon->optGen['monitoring_ack_svc'])) {
                $process_service_checked = 'checked';
            }
            $template->assign('process_service_checked', $process_service_checked);

            $force_active_checked = '';
            if (! empty($centreon->optGen['monitoring_ack_active_checks'])) {
                $force_active_checked = 'checked';
            }
            $template->assign('force_active_checked', $force_active_checked);

            $template->display('acknowledge.ihtml');
        } elseif ($cmd == 75) {
            $template->assign('downtimeHostSvcLabel', _('Set downtime on services of hosts'));
            $template->assign('defaultMessage', sprintf(_('Downtime set by %s'), $centreon->user->alias));
            $template->assign('titleLabel', _('Host Downtime'));
            $template->assign('submitLabel', _('Set Downtime'));
            $template->assign('defaultDuration', $defaultDuration);
            $template->assign('sDurationLabel', _('seconds'));
            $template->assign('mDurationLabel', _('minutes'));
            $template->assign('hDurationLabel', _('hours'));
            $template->assign('dDurationLabel', _('days'));
            $template->assign($defaultScale . 'DefaultScale', 'selected');

            // default downtime options
            $fixed_checked = '';
            if (! empty($centreon->optGen['monitoring_dwt_fixed'])) {
                $fixed_checked = 'checked';
            }
            $template->assign('fixed_checked', $fixed_checked);

            $process_service_checked = '';
            if (! empty($centreon->optGen['monitoring_dwt_svc'])) {
                $process_service_checked = 'checked';
            }
            $template->assign('process_service_checked', $process_service_checked);
            $template->display('downtime.ihtml');
        }
    } else {
        $command = '';
        switch ($cmd) {
            // remove ack
            case 73:
                $command = 'REMOVE_HOST_ACKNOWLEDGEMENT;%s';
                break;
                // enable notif
            case 82:
                $command = 'ENABLE_HOST_NOTIFICATIONS;%s';
                break;
                // disable notif
            case 83:
                $command = 'DISABLE_HOST_NOTIFICATIONS;%s';
                break;
                // enable check
            case 92:
                $command = 'ENABLE_HOST_CHECK;%s';
                break;
                // disable check
            case 93:
                $command = 'DISABLE_HOST_CHECK;%s';
                break;
            default:
                throw new Exception('Unknown command');
                break;
        }
        if ($command != '') {
            $externalCommandMethod = 'set_process_command';
            if (method_exists($externalCmd, 'setProcessCommand')) {
                $externalCommandMethod = 'setProcessCommand';
            }
            foreach ($hosts as $hostId) {
                $hostId = filter_var($hostId, FILTER_VALIDATE_INT) ?: 0;
                if ($hostId !== 0) {
                    $externalCmd->{$externalCommandMethod}(
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
    echo $e->getMessage() . '<br/>';
}
?>
<div id='result'></div>

<script type='text/javascript'>
    var result = <?php echo $result; ?>;
    var successMsg = "<?php echo $successMsg; ?>";

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
