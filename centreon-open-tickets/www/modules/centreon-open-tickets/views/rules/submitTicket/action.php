<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

require_once realpath(__DIR__ . '/../../../../../../config/centreon.config.php');
require_once realpath(__DIR__ . '/../../../../../../vendor/autoload.php');
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonDB.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/class/centreonUtils.class.php';
require_once $centreon_path . 'www/class/centreonXMLBGRequest.class.php';
require_once $centreon_path . 'www/modules/centreon-open-tickets/class/rule.php';

session_start();
$centreon_bg = new CentreonXMLBGRequest($dependencyInjector, session_id(), 1, 1, 0, 1);

?>

<script type="text/javascript" src="./modules/centreon-open-tickets/lib/jquery.serialize-object.min.js"></script>
<script type="text/javascript" src="./modules/centreon-open-tickets/lib/commonFunc.js"></script>
<script type="text/javascript" src="./modules/centreon-open-tickets/lib/dropzone.js"></script>
<link href="./modules/centreon-open-tickets/lib/dropzone.css" rel="stylesheet" type="text/css"/>

<?php

const SERVICE_OPEN_TICKET_COMMAND_ID = 3;
const HOST_OPEN_TICKET_COMMAND_ID = 4;

function format_popup(): void
{
    global $cmd,
           $rule,
           $centreon,
           $centreon_path,
           $db;

    $rules = [];

    $statement = $db->query('SELECT rule_id, alias, provider_id FROM mod_open_tickets_rule');

    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        $rules[$row['rule_id']] = $row;
    }

    $uniqId = uniqid();

    $title = $cmd === SERVICE_OPEN_TICKET_COMMAND_ID
        ? _('Open Service Ticket')
        : _('Open Host Ticket');

    $result = null;

    if (isset($_GET['rule_id'])) {
        $selection = $_GET['selection'] ?? $_GET['host_id'] . ';' . $_GET['service_id'];
        $result = $rule->getFormatPopupProvider(
            $_GET['rule_id'],
            [
                'title' => $title,
                'user' => [
                    'name' => $centreon->user->name,
                    'alias' => $centreon->user->alias,
                    'email' => $centreon->user->email,
                ],
            ],
            0,
            $uniqId,
            $_GET['cmd'],
            $selection
        );
    }

    $path = $centreon_path . 'www/widgets/open-tickets/src/';
    $template = new Smarty();
    $template = initSmartyTplForPopup($path . 'templates/', $template, './', $centreon_path);

    if (isset($_GET['rule_id'])) {
        $template->assign('provider_id', $rules[$_GET['rule_id']]['provider_id']);
        $template->assign('rule_id', $_GET['rule_id']);
        $template->assign('widgetId', 0);
        $template->assign('uniqId', $uniqId);
        $template->assign('title', $title);
        $template->assign('cmd', $cmd);
        $template->assign('selection', $selection);
        $template->assign('continue', (! is_null($result) && isset($result['format_popup'])) ? 0 : 1);
        $template->assign(
            'attach_files_enable',
            (! is_null($result)
                && isset($result['attach_files_enable'])
                && $result['attach_files_enable'] === 'yes'
            ) ? 1 : 0
        );

        $template->assign(
            'formatPopupProvider',
            (! is_null($result)
                && isset($result['format_popup'])
            ) ? $result['format_popup'] : ''
        );

        $template->assign('submitLabel', _('Open'));
    } else {
        $template->assign('rules', $rules);
        $template->assign('submitRule', _('Select'));
    }

    $template->display(__DIR__ . '/submitTicket.ihtml');
}

try {
    if (! isset($_SESSION['centreon']) || ! isset($_GET['cmd'])) {
        throw new Exception('Missing data');
    }
    $db = new CentreonDB();
    if (CentreonSession::checkSession(session_id(), $db) === 0) {
        throw new Exception('Invalid session');
    }
    /** @var Centreon $centreon */
    $centreon = $_SESSION['centreon'];
    $oreon = $centreon->user;

    $cmd = filter_input(INPUT_GET, 'cmd', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);

    $widgetId = filter_input(INPUT_GET, 'widgetId', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
    $selections = explode(',', $_REQUEST['selection']);

    $widgetObj = new CentreonWidget($centreon, $db);
    $preferences = $widgetObj->getWidgetPreferences($widgetId);

    $rule = new Centreon_OpenTickets_Rule($db);

    if (
        $cmd === SERVICE_OPEN_TICKET_COMMAND_ID
        || $cmd === HOST_OPEN_TICKET_COMMAND_ID
    ) {
        format_popup();
    } else {
        throw new Exception('Unhandled data provided for cmd parameter');
    }
} catch (Exception $e) {
    echo $e->getMessage() . '<br/>';
}
?>
