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
require_once $centreon_path . 'bootstrap.php';
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/class/centreonDuration.class.php';
require_once $centreon_path . 'www/class/centreonUtils.class.php';

session_start();
if (! isset($_SESSION['centreon']) || ! isset($_POST['widgetId'])) {
    exit;
}

// Smarty template initialization
$path = $centreon_path . 'www/widgets/host-monitoring/src/';
$template = SmartyBC::createSmartyTemplate($path, './');

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
$actions = "<option value='0'>-- " . _('More actions') . ' -- </option>';
if ($canDoAction || $centreon->user->access->checkAction('host_acknowledgement')) {
    $actions .= "<option value='72'>" . _('Acknowledge') . '</option>';
}
if ($canDoAction || $centreon->user->access->checkAction('host_disacknowledgement')) {
    $actions .= "<option value='73'>" . _('Remove Acknowledgement') . '</option>';
}
if ($canDoAction || $centreon->user->access->checkAction('host_schedule_downtime')) {
    $actions .= "<option value='75'>" . _('Set Downtime') . '</option>';
}
if ($canDoAction || $centreon->user->access->checkAction('host_notifications')) {
    $actions .= "<option value='82'>" . _('Enable Host Notification') . '</option>';
    $actions .= "<option value='83'>" . _('Disable Host Notification') . '</option>';
}
if ($canDoAction || $centreon->user->access->checkAction('host_checks')) {
    $actions .= "<option value='92'>" . _('Enable Host Check') . '</option>';
    $actions .= "<option value='93'>" . _('Disable Host Check') . '</option>';
}

$template->assign('widgetId', $widgetId);
$template->assign('actions', $actions);
$template->display('toolbar.ihtml');

?>
<script type="text/javascript" src="../../include/common/javascript/centreon/popin.js"></script>
<script type='text/javascript'>

    var tab = new Array();
    var actions = "<?php echo $actions; ?>";
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
