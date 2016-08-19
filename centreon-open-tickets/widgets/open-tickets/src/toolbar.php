<?php
/*
 * Copyright 2015 Centreon (http://www.centreon.com/)
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

require_once $centreon_path ."GPL_LIB/Smarty/libs/Smarty.class.php";

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
$canDoAction = true;

$actions  = "<option value='0'>-- "._("More actions")." -- </option>";

if (!isset($preferences['opened_tickets']) || $preferences['opened_tickets'] == 0) {
    if ($canDoAction) { // || $centreon->user->access->checkAction("service_schedule_check")) {
        $actions .= "<option value='3'>"._("Service: Open ticket")."</option>";
    }
    if ($canDoAction) { // || $centreon->user->access->checkAction("host_acknowledgement")) {
        $actions .= "<option value='4'>"._("Host: Open ticket")."</option>";
    }
} else {
    $actions .= "<option value='10'>" . _("Close Tickets") . "</option>";
}

$template->assign("widgetId", $widgetId);
$template->display('toolbar.ihtml');

//<link href="../../include/common/javascript/jquery/plugins/colorbox/colorbox.css" rel="stylesheet" type="text/css"/>
//<script type="text/javascript" src="../../include/common/javascript/jquery/plugins/colorbox/jquery.colorbox-min.js"></script>

?>

<script type="text/javascript" src="../../include/common/javascript/centreon/popin.js"></script>

<script type='text/javascript'>
var tab = new Array();
var sid = '<?php echo session_id();?>';
var actions = "<?php echo $actions;?>";
var widget_id = "<?php echo $widgetId; ?>";

$(function() {
	$(".toolbar").html(actions);
	$(".toolbar").change(function() {
		if (jQuery(this).val() != 0) {
    		var checkValues = $("input:checked").map(function() {
    			var tmp = $(this).attr('id').split("_");
    			return tmp[1];
    		}).get().join(",");
    		
            if (checkValues != '') {
                var url = "./widgets/open-tickets/src/action.php?widgetId="+widget_id+"&sid="+sid+"&selection="+checkValues+"&cmd="+jQuery(this).val();
                // We delete the old one (not really clean. Should be managed by popin itself. Like with a destroy parameters)
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
});
</script>