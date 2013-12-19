<?php
/**
 * Copyright 2005-2011 MERETHIS
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
 * As a special exception, the copyright holders of this program give MERETHIS
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of MERETHIS choice, provided that
 * MERETHIS also meet, for each linked independent module, the terms  and conditions
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
    if (!isset($_SESSION['centreon']) || !isset($_REQUEST['cmd']) || !isset($_REQUEST['sid']) || !isset($_REQUEST['selection'])) {
        throw new Exception('Missing data');
    }
    $db = new CentreonDB();
    if (CentreonSession::checkSession(session_id(), $db) == 0) {
        throw new Exception('Invalid session');
    }
    $centreon = $_SESSION['centreon'];
    $oreon = $centreon;
    $cmd = $_REQUEST['cmd'];
    $hosts = explode(",", $_REQUEST['selection']);
    $externalCmd = new CentreonExternalCommand($centreon);
    $hostObj = new CentreonHost($db);
    $successMsg = _("External Command successfully submitted... Exiting window...");
    $result = 0;

    if ($cmd == 72 || $cmd == 75) {
        require_once $centreon_path ."GPL_LIB/Smarty/libs/Smarty.class.php";
        $path = $centreon_path . "www/widgets/host-monitoring/src/";
        $template = new Smarty();
        $template = initSmartyTplForPopup($path, $template, "./", $centreon_path);
        $template->assign('stickyLabel', _("Sticky"));
        $template->assign('persistentLabel', _("Persistent"));
        $template->assign('authorLabel', _("Author"));
        $template->assign('notifyLabel', _("Notify"));
        $template->assign('commentLabel', _("Comment"));
        $template->assign('fixedLabel', _("Fixed"));
        $template->assign('durationLabel', _("Duration"));
        $template->assign('startLabel', _("Start"));
        $template->assign('endLabel', _("End"));
        $template->assign('hosts', $_REQUEST['selection']);
        $template->assign('author', $centreon->user->name);
        if ($cmd == 72) {
            $template->assign('ackHostSvcLabel', _("Acknowledge services of hosts"));
            $template->assign('titleLabel', _("Host Acknowledgement"));
            $template->assign('submitLabel', _("Acknowledge"));
            $template->display('acknowledge.ihtml');
        } elseif ($cmd == 75) {
            $template->assign('downtimeHostSvcLabel', _("Set downtime on services of hosts"));
            $template->assign('titleLabel', _("Host Downtime"));
            $template->assign('submitLabel', _("Set Downtime"));
            $template->assign('defaultDuration', 1);
            $template->assign('daysLabel', _("days"));
            $template->assign('hoursLabel', _("hours"));
            $template->assign('minutesLabel', _("minutes"));
            $template->assign('defaultStart', date('Y/m/d'));
            $template->assign('defaultHourStart', date('H'));
            $template->assign('defaultMinuteStart', date('i'));
            $endTime = time() + 7200;
            $template->assign('defaultEnd', date('Y/m/d', $endTime));
            $template->assign('defaultHourEnd', date('H', $endTime));
            $template->assign('defaultMinuteEnd', date('i', $endTime));
            $template->display('downtime.ihtml');
        }
    } else {
        $command = "";
        switch ($cmd) {
                /* remove ack */
                case 73 :
                    $command = "REMOVE_HOST_ACKNOWLEDGEMENT;%s";
                    break;
                /* enable notif */
                case 82 :
                    $command = "ENABLE_HOST_NOTIFICATIONS;%s";
                    break;
                /* disable notif */
                case 83 :
                    $command = "DISABLE_HOST_NOTIFICATIONS;%s";
                    break;
                /* enable check */
                case 92 :
                    $command = "ENABLE_HOST_CHECK;%s";
                    break;
                /* disable check */
                case 93 :
                    $command = "DISABLE_HOST_CHECK;%s";
                    break;
                default :
                    throw new Exception('Unknown command');
                    break;
        }
        if ($command != "") {
            foreach ($hosts as $hostId) {
                $externalCmd->set_process_command(sprintf($command, $hostObj->getHostName($hostId)), $hostObj->getHostPollerId($hostId));
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
<script type="text/javascript" src="../../../include/common/javascript/jquery/jquery.js"></script>
<script type="text/javascript" src="../../../include/common/javascript/jquery/jquery-ui.js"></script>
<script type="text/javascript" src="../../../include/common/javascript/widgetUtils.js"></script>
<script type='text/javascript'>
var result = <?php echo $result;?>;
var successMsg = "<?php echo $successMsg;?>";

$(function() {
	if (result) {
		$("#result").html(successMsg);
		setTimeout('closeBox()', 2000);
	}
	$("#submit").click(function() {
			sendCmd();
	});
	$("#ListTable").styleTable();
	$("#submit").button();
	toggleDurationField();
	$("[name=fixed]").click(function() {
		toggleDurationField();
	});
	$("#downtimestart,#downtimeend").datepicker({ dateFormat: 'yy/mm/dd' });
});

function closeBox()
{
	parent.jQuery.colorbox.close();
}

function sendCmd()
{
	fieldResult = true;
	if ($("#comment") && !$("#comment").val()) {
		fieldResult = false;
	}
	if (fieldResult == false) {
		$("#result").html("<font color=red><b>Please fill all mandatory fields.</b></font>");
		return false;
	}
	$.ajax({
				type	:	"POST",
				url		:	"sendCmd.php",
				data	: 	$("#Form").serialize(),
				success	:	function() {
								$("#result").html(successMsg);
								setTimeout('closeBox()', 2000);
							}
		   });
}

function toggleDurationField()
{
	if ($("[name=fixed]").is(':checked')) {
		$("[name=dayduration]").attr('disabled', true);
		$("[name=hourduration]").attr('disabled', true);
		$("[name=minuteduration]").attr('disabled', true);
	} else {
		$("[name=dayduration]").removeAttr('disabled');
		$("[name=hourduration]").removeAttr('disabled');
		$("[name=minuteduration]").removeAttr('disabled');
	}
}
</script>