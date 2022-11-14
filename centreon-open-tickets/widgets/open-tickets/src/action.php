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

function service_ack()
{
    global $cmd, $centreon, $centreon_path;

    $path = $centreon_path . "www/widgets/open-tickets/src/";
    $template = new Smarty();
    $template = initSmartyTplForPopup($path . 'templates/', $template, "./", $centreon_path);
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
    $template->display('acknowledge.ihtml');
}

function format_popup()
{
    global $cmd, $widgetId, $rule, $preferences, $centreon, $centreon_path;

    $uniq_id = uniqid();
    if ($cmd == 3) {
        $title = _("Open Service Ticket");
    } else {
        $title = _("Open Host Ticket");
    }

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

    $path = $centreon_path . "www/widgets/open-tickets/src/";
    $template = new Smarty();
    $template = initSmartyTplForPopup($path . 'templates/', $template, "./", $centreon_path);

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

    $template = new Smarty();
    $template = initSmartyTplForPopup($path . 'templates/', $template, "./", $centreon_path);
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
        service_ack();
    }
} catch (Exception $e) {
    echo $e->getMessage() . "<br/>";
}
?>
