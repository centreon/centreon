<?php
/*
 * Copyright 2015-2019 Centreon (http://www.centreon.com/)
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
require_once $centreon_path . 'www/class/centreonUtils.class.php';
require_once $centreon_path . "www/class/centreonXMLBGRequest.class.php";
require_once $centreon_path . 'www/modules/centreon-open-tickets/class/rule.php';

session_start();
$centreon_bg = new CentreonXMLBGRequest($dependencyInjector, session_id(), 1, 1, 0, 1);

?>

<script type="text/javascript" src="./modules/centreon-open-tickets/lib/jquery.serialize-object.min.js"></script>
<script type="text/javascript" src="./modules/centreon-open-tickets/lib/commonFunc.js"></script>
<script type="text/javascript" src="./modules/centreon-open-tickets/lib/dropzone.js"></script>
<link href="./modules/centreon-open-tickets/lib/dropzone.css" rel="stylesheet" type="text/css"/>

<?php

/**
 * service_ack: put an ack on a host and service
 * @param bool $autoCloseActionPopup set to 1 if you want to automatically close call popup
 */
function service_ack(bool $autoCloseActionPopup)
{
    global $cmd, $centreon, $centreon_path;

    // Smarty template initialization
    $path = $centreon_path . "www/widgets/open-tickets/src/";
    $template = SmartyBC::createSmartyTemplate($path . 'templates/', './');

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

    $title = _("Service Acknowledgement");
    $template->assign('defaultMessage', sprintf(_('Acknowledged by %s'), $centreon->user->alias));
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
    $template->assign('autoCloseActionPopup', $autoCloseActionPopup);
    $template->display('acknowledge.ihtml');
}

/**
 * schedule_check: prepare variables for widget popup when scheduling a check
 *
 * @param bool $isService set to true if you want to schedule a check on a service
 * @param bool $isForced set to true if you want to schedule a forced check
 * @param bool $autoCloseActionPopup set to true if you want to automatically close call popup
 */
function schedule_check(bool $isService, bool $isForced, bool $autoCloseActionPopup)
{
    global $cmd, $centreon, $centreon_path;

    $selection = filter_var($_REQUEST['selection'], FILTER_SANITIZE_STRING);

    // Smarty template initialization
    $path = $centreon_path . "www/widgets/open-tickets/src/";
    $template = SmartyBC::createSmartyTemplate($path . 'templates/', './');

    $template->assign('selection', $selection);
    $template->assign('titleLabel', _("Scheduling checks"));
    $template->assign('forced', $isForced);
    $template->assign('isService', $isService);
    $template->assign('autoCloseActionPopup', $autoCloseActionPopup ? 'true' : 'false');
    $template->display('schedulecheck.ihtml');
}

function format_popup()
{
    global $cmd, $widgetId, $rule, $preferences, $centreon, $centreon_path;

    $uniq_id = uniqid();
    $title = $cmd == 3 ? _("Open Service Ticket") : _("Open Host Ticket");

    $result = $rule->getFormatPopupProvider(
        $preferences['rule'],
        [
            'title' => $title,
            'user' => [
                'name' => $centreon->user->name,
                'alias' => $centreon->user->alias,
                'email' => $centreon->user->email
            ]
        ],
        $widgetId,
        $uniq_id,
        $_REQUEST['cmd'],
        $_REQUEST['selection']
    );

    // Smarty template initialization
    $path = $centreon_path . "www/widgets/open-tickets/src/";
    $template = SmartyBC::createSmartyTemplate($path . 'templates/', './');

    $provider_infos = $rule->getAliasAndProviderId($preferences['rule']);

    $template->assign('provider_id', $provider_infos['provider_id']);
    $template->assign('rule_id', $preferences['rule']);
    $template->assign('widgetId', $widgetId);
    $template->assign('uniqId', $uniq_id);
    $template->assign('title', $title);
    $template->assign('cmd', $cmd);
    $template->assign('selection', $_REQUEST['selection']);
    $template->assign('continue', (!is_null($result) && isset($result['format_popup'])) ? 0 : 1);
    $template->assign(
        'attach_files_enable',
        (!is_null($result)
            && isset($result['attach_files_enable'])
            && $result['attach_files_enable'] === 'yes'
        ) ? 1 : 0
    );

    $template->assign(
        'formatPopupProvider',
        (!is_null($result)
            && isset($result['format_popup'])
        ) ? $result['format_popup'] : ''
    );

    $template->assign('submitLabel', _("Open"));

    $template->display('formatpopup.ihtml');
}

function remove_tickets()
{
    global $cmd, $widgetId, $rule, $preferences, $centreon, $centreon_path, $centreon_bg;

    $path = $centreon_path . "www/widgets/open-tickets/src/";
    $provider_infos = $rule->getAliasAndProviderId($preferences['rule']);

    // Smarty template initialization
    $template = SmartyBC::createSmartyTemplate($path . 'templates/', './');

    $template->assign('title', _('Close Tickets'));
    $template->assign('selection', $_REQUEST['selection']);
    $template->assign('provider_id', $provider_infos['provider_id']);
    $template->assign('rule_id', $preferences['rule']);

    $template->display('removetickets.ihtml');
}

try {
    if (!isset($_SESSION['centreon']) || !isset($_REQUEST['cmd']) || !isset($_REQUEST['selection'])) {
        throw new Exception('Missing data');
    }
    $db = new CentreonDB();
    if (CentreonSession::checkSession(session_id(), $db) == 0) {
        throw new Exception('Invalid session');
    }
    /** @var \Centreon $centreon */
    $centreon = $_SESSION['centreon'];
    $oreon = $centreon;
    $cmd = $_REQUEST['cmd'];

    $widgetId = $_REQUEST['widgetId'];
    $selections = explode(",", $_REQUEST['selection']);

    $widgetObj = new CentreonWidget($centreon, $db);
    $preferences = $widgetObj->getWidgetPreferences($widgetId);

    $rule = new Centreon_OpenTickets_Rule($db);

    if ($cmd == 3 || $cmd == 4) {
        format_popup();
    } elseif ($cmd == 10) {
        remove_tickets();
    } elseif ($cmd == 70) {
        service_ack((bool) $preferences['auto_close_action_popup']);
    //schedule service forced check
    } elseif ($cmd == 80) {
        schedule_check(true, true, (bool) $preferences['auto_close_action_popup']);
    // schedule service check
    } elseif ($cmd == 81) {
        schedule_check(true, false, (bool) $preferences['auto_close_action_popup']);
    // schedule host forced check
    } elseif ($cmd == 82) {
        schedule_check(false, true, (bool) $preferences['auto_close_action_popup']);
    // schedule host check
    } elseif ($cmd == 83) {
        schedule_check(false, false, (bool) $preferences['auto_close_action_popup']);
    }
} catch (Exception $e) {
    echo $e->getMessage() . "<br/>";
}
?>
