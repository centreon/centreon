<?php

/*
 * Copyright 2005-2021 Centreon
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

if (! isset($centreon)) {
    exit();
}

require_once _CENTREON_PATH_ . 'www/class/centreonGMT.class.php';
require_once _CENTREON_PATH_ . 'www/include/common/sqlCommonFunction.php';
require_once './include/common/autoNumLimit.php';

$search_service = null;
$host_name = null;
$search_output = null;
$canViewAll = false;
$canViewDowntimeCycle = false;
$serviceType = 1;
$hostType = 2;

if (isset($_POST['SearchB'])) {
    $centreon->historySearch[$url] = array();
    $search_service = isset($_POST['search_service'])
        ? HtmlSanitizer::createFromString($_POST['search_service'])->removeTags()->getString()
        : null;
    $centreon->historySearch[$url]['search_service'] = $search_service;

    $host_name = isset($_POST['search_host'])
        ? HtmlSanitizer::createFromString($_POST['search_host'])->removeTags()->getString()
        : null;
    $centreon->historySearch[$url]['search_host'] = $host_name;

    $search_output = isset($_POST['search_output'])
        ? HtmlSanitizer::createFromString($_POST['search_output'])->removeTags()->getString()
        : null;
    $centreon->historySearch[$url]['search_output'] = $search_output;

    $search_author = isset($_POST['search_author'])
        ? HtmlSanitizer::createFromString($_POST['search_author'])->removeTags()->getString()
        : null;
    $centreon->historySearch[$url]['search_author'] = $search_author;

    $canViewAll = isset($_POST['view_all']) ? true : false;
    $centreon->historySearch[$url]['view_all'] = $canViewAll;
    $canViewDowntimeCycle = isset($_POST['view_downtime_cycle']) ? true : false;
    $centreon->historySearch[$url]['view_downtime_cycle'] = $canViewDowntimeCycle;
} elseif (isset($_GET['SearchB'])) {
    $centreon->historySearch[$url] = [];

    $search_service = isset($_GET['search_service'])
        ? HtmlSanitizer::createFromString($_GET['search_service'])->removeTags()->getString()
        : null;
    $centreon->historySearch[$url]['search_service'] = $search_service;

    $host_name = isset($_GET['search_host'])
        ? HtmlSanitizer::createFromString($_GET['search_host'])->removeTags()->getString()
        : null;
    $centreon->historySearch[$url]['search_host'] = $host_name;

    $search_output = isset($_GET['search_output'])
        ? HtmlSanitizer::createFromString($_GET['search_output'])->removeTags()->getString()
        : null;
    $centreon->historySearch[$url]['search_output'] = $search_output;

    $search_author = isset($_GET['search_author'])
        ? HtmlSanitizer::createFromString($_GET['search_author'])->removeTags()->getString()
        : null;
    $centreon->historySearch[$url]['search_author'] = $search_author;

    $canViewAll = isset($_GET['view_all']) ? true : false;
    $centreon->historySearch[$url]['view_all'] = $canViewAll;
    $canViewDowntimeCycle = isset($_GET['view_downtime_cycle']) ? true : false;
    $centreon->historySearch[$url]['view_downtime_cycle'] = $canViewDowntimeCycle;
} else {
    $search_service = $centreon->historySearch[$url]['search_service'] ?? null;
    $host_name = $centreon->historySearch[$url]['search_host'] ?? null;
    $search_output = $centreon->historySearch[$url]['search_output'] ?? null;
    $search_author = $centreon->historySearch[$url]['search_author'] ?? null;
    $canViewAll = $centreon->historySearch[$url]['view_all'] ?? false;
    $canViewDowntimeCycle = $centreon->historySearch[$url]['view_downtime_cycle'] ?? false;
}

// Init GMT class
$centreonGMT = new CentreonGMT($pearDB);
$centreonGMT->getMyGMTFromSession(session_id(), $pearDB);

/**
 * true: URIs will correspond to deprecated pages
 * false: URIs will correspond to new page (Resource Status)
 */
$useDeprecatedPages = $centreon->user->doesShowDeprecatedPages();

include_once './class/centreonDB.class.php';

$kernel = \App\Kernel::createForWeb();
$resourceController = $kernel->getContainer()->get(
    \Centreon\Application\Controller\MonitoringResourceController::class
);

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate('./include/monitoring/downtime/', 'template/');

$form = new HTML_QuickFormCustom('select_form', 'GET', '?p=' . $p);

$tab_downtime_svc = array();

$attrBtnSuccess = ['class' => 'btc bt_success', 'onClick' => "window.history.replaceState('', '', '?p=" . $p . "');"];
$form->addElement('submit', 'SearchB', _('Search'), $attrBtnSuccess);

// ------------------ BAM ------------------
$tab_service_bam = [];
$pdoStatement = $pearDB->executeQuery("SELECT id FROM modules_informations WHERE name = 'centreon-bam-server'");

if ($pdoStatement->rowCount() > 0) {
    $pdoStatement = $pearDB->executeQuery("SELECT CONCAT('ba_', ba_id) AS id, ba_id, name FROM mod_bam");

    while (($elem = $pearDB->fetch($pdoStatement)) !== false) {
        $tab_service_bam[$elem['id']] = ['name' => $elem['name'], 'id' => $elem['ba_id']];
    }
}

// Service Downtimes
$extraFields = '';
if ($canViewAll) {
    $extraFields = ', actual_end_time, cancelled as was_cancelled ';
}

$bindValues = [
    ':offset' => [$num * $limit, PDO::PARAM_INT],
    ':limit' => [$limit, PDO::PARAM_INT],
];
$serviceAclSubRequest = '';
$hostAclSubRequest = '';

if (! $is_admin) {
    if ($centreon->user->access->getAccessGroups() !== []) {
        [$aclBindValues, $aclQuery] = createMultipleBindQuery(array_keys($centreon->user->access->getAccessGroups()), ':group_id');

        foreach ($aclBindValues as $key => $value) {
            $bindValues[$key] = [$value, PDO::PARAM_INT];
        }
    } else {
        $aclQuery = '-1';
    }
    $serviceAclSubRequest = <<<SQL

        INNER JOIN centreon_acl acl
            ON acl.host_id = s.host_id
            AND acl.service_id = s.service_id
            AND acl.group_id IN ({$aclQuery})
        SQL;

    $hostAclSubRequest = <<<SQL

        INNER JOIN centreon_acl acl
            ON acl.host_id = h.host_id
            AND acl.service_id IS NULL
            AND acl.group_id IN ({$aclQuery})
        SQL;
}

$subQueryConditionCancelled = (! $canViewAll) ? ' AND d.cancelled = 0 ' : '';

$subQueryConditionSearchService = '';
if (isset($search_service) && $search_service !== '') {
    $subQueryConditionSearchService = 'AND s.description LIKE :service ';
    $bindValues[':service'] = ['%' . $search_service . '%', PDO::PARAM_STR];
}

$subQueryConditionSearchOutput = '';
if (isset($search_output) && $search_output !== '') {
    $subQueryConditionSearchOutput = 'AND d.comment_data LIKE :output ';
    $bindValues[':output'] = ['%' . $search_output . '%', PDO::PARAM_STR];
} elseif ($canViewDowntimeCycle === false) {
    $subQueryConditionSearchOutput = " AND d.comment_data NOT LIKE '%Downtime cycle%' ";
}

$subQueryConditionSearchAuthor = '';
if (isset($search_author) && $search_author !== '') {
    $subQueryConditionSearchAuthor = 'AND d.author LIKE :author ';
    $bindValues[':author'] = ['%' . $search_author . '%', PDO::PARAM_STR];
}

$subQueryConditionSearchHost = '';
if (isset($host_name) && $host_name !== '') {
    $subQueryConditionSearchHost = 'AND h.name LIKE :host ';
    $bindValues[':host'] = ['%' . $host_name . '%', PDO::PARAM_STR];
}

$subQueryConditionSearchEndTime = '';
if ($canViewAll === false) {
    $subQueryConditionSearchEndTime = 'AND d.end_time > :end_time ';
    $bindValues[':end_time'] = [time(), PDO::PARAM_INT];
}

$serviceQuery = <<<SQL
    SELECT SQL_CALC_FOUND_ROWS DISTINCT
        1 AS REALTIME, d.internal_id as internal_downtime_id, d.entry_time, duration,
        d.author as author_name, d.comment_data, d.fixed as is_fixed, d.start_time as scheduled_start_time,
        d.end_time as scheduled_end_time, d.started as was_started, d.host_id, d.service_id, h.name as host_name,
        s.description as service_description {$extraFields}
    FROM downtimes d
    INNER JOIN services s
        ON d.host_id = s.host_id
        AND d.service_id = s.service_id
    INNER JOIN hosts h
        ON h.host_id = s.host_id
    {$serviceAclSubRequest}
    WHERE d.type = {$serviceType}
        {$subQueryConditionCancelled}
        {$subQueryConditionSearchService}
        {$subQueryConditionSearchOutput}
        {$subQueryConditionSearchAuthor}
        {$subQueryConditionSearchHost}
        {$subQueryConditionSearchEndTime}
    SQL;

$hostQuery = <<<SQL
    SELECT DISTINCT
        1 AS REALTIME, d.internal_id as internal_downtime_id, d.entry_time, duration,
        d.author as author_name, d.comment_data, d.fixed as is_fixed, d.start_time as scheduled_start_time,
        d.end_time as scheduled_end_time, d.started as was_started, d.host_id, d.service_id, h.name as host_name,
        '' as service_description {$extraFields}
    FROM downtimes d
    INNER JOIN hosts h
        ON d.host_id = h.host_id
    {$hostAclSubRequest}
    WHERE d.type = {$hostType}
        {$subQueryConditionCancelled}
        {$subQueryConditionSearchOutput}
        {$subQueryConditionSearchAuthor}
        {$subQueryConditionSearchHost}
        {$subQueryConditionSearchEndTime}
    SQL;

$unionQuery = <<<SQL
    ({$serviceQuery})
    UNION
    ({$hostQuery})
    ORDER BY scheduled_start_time DESC
    LIMIT :offset, :limit
    SQL;

$downtimesStatement = $pearDBO->prepareQuery($unionQuery);
$pearDBO->executePreparedQuery($downtimesStatement, $bindValues, true);

$rows = $pearDBO->fetchColumn($pearDBO->executeQuery('SELECT FOUND_ROWS() AS REALTIME'));

for ($i = 0; ($data = $pearDBO->fetch($downtimesStatement)) !== false; $i++) {
    $tab_downtime_svc[$i] = $data;

    $tab_downtime_svc[$i]['comment_data']
        = CentreonUtils::escapeAllExceptSelectedTags($data['comment_data']);

    $tab_downtime_svc[$i]['scheduled_start_time'] = $tab_downtime_svc[$i]['scheduled_start_time'] . ' ';
    $tab_downtime_svc[$i]['scheduled_end_time'] = $tab_downtime_svc[$i]['scheduled_end_time'] . ' ';

    if (preg_match('/_Module_BAM_\d+/', $data['host_name'])) {
        $tab_downtime_svc[$i]['host_name'] = 'Module BAM';
        $tab_downtime_svc[$i]['h_details_uri'] = './main.php?p=207&o=d&ba_id='
            . $tab_service_bam[$data['service_description']]['id'];
        $tab_downtime_svc[$i]['s_details_uri'] = './main.php?p=207&o=d&ba_id='
            . $tab_service_bam[$data['service_description']]['id'];
        $tab_downtime_svc[$i]['service_description'] = $tab_service_bam[$data['service_description']]['name'];
        $tab_downtime_svc[$i]['downtime_type'] = 'SVC';
        if ($tab_downtime_svc[$i]['author_name'] === 'Centreon Broker BAM Module') {
            $tab_downtime_svc[$i]['scheduled_end_time'] = 'Automatic';
            $tab_downtime_svc[$i]['duration'] = 'Automatic';
        }
    } else {
        $tab_downtime_svc[$i]['host_name'] = $data['host_name'];
        $tab_downtime_svc[$i]['h_details_uri'] = $useDeprecatedPages
            ? './main.php?p=20202&o=hd&host_name=' . $data['host_name']
            : $resourceController->buildHostDetailsUri($data['host_id']);
        if ($data['service_description'] !== '') {
            $tab_downtime_svc[$i]['s_details_uri'] = $useDeprecatedPages
            ? './main.php?p=202&o=svcd&host_name='
                . $data['host_name']
                . '&service_description='
                . $data['service_description']
            : $resourceController->buildServiceDetailsUri(
                $data['host_id'],
                $data['service_id']
            );
            $tab_downtime_svc[$i]['service_description'] = $data['service_description'];
            $tab_downtime_svc[$i]['downtime_type'] = 'SVC';
        } else {
            $tab_downtime_svc[$i]['service_description'] = '-';
            $tab_downtime_svc[$i]['downtime_type'] = 'HOST';
        }
    }
}
unset($data);

include './include/common/checkPagination.php';

$en = ['0' => _('No'), '1' => _('Yes')];
foreach ($tab_downtime_svc as $key => $value) {
    $tab_downtime_svc[$key]['is_fixed'] = $en[$tab_downtime_svc[$key]['is_fixed']];
    $tab_downtime_svc[$key]['was_started'] = $en[$tab_downtime_svc[$key]['was_started']];
    if ($canViewAll) {
        if (! isset($tab_downtime_svc[$key]['actual_end_time']) || ! $tab_downtime_svc[$key]['actual_end_time']) {
            if ($tab_downtime_svc[$key]['was_cancelled'] === 0) {
                $tab_downtime_svc[$key]['actual_end_time'] = _('N/A');
            } else {
                $tab_downtime_svc[$key]['actual_end_time'] = _('Never Started');
            }
        } else {
            $tab_downtime_svc[$key]['actual_end_time'] = $tab_downtime_svc[$key]['actual_end_time'] . ' ';
        }
        $tab_downtime_svc[$key]['was_cancelled'] = $en[$tab_downtime_svc[$key]['was_cancelled']];
    }
}
// Element we need when we reload the page
$form->addElement('hidden', 'p');
$tab = ['p' => $p];
$form->setDefaults($tab);

if ($centreon->user->access->checkAction('host_schedule_downtime')) {
    $tpl->assign('msgs2', ['addL2' => '?p=' . $p . '&o=a', 'addT2' => _('Add a downtime'), 'delConfirm' => addslashes(_('Do you confirm the cancellation ?'))]);
}

$tpl->assign('p', $p);
$tpl->assign('o', $o);

$tpl->assign('tab_downtime_svc', $tab_downtime_svc);
$tpl->assign('nb_downtime_svc', count($tab_downtime_svc));
$tpl->assign('dtm_service_downtime', _('Services Downtimes'));
$tpl->assign('secondes', _('s'));
$tpl->assign('view_host_dtm', _('View downtimes of hosts'));
$tpl->assign('host_dtm_link', './main.php?p=' . $p . '&o=vh');
$tpl->assign('cancel', _('Cancel'));
$tpl->assign('limit', $limit);

$tpl->assign('Host', _('Host Name'));
$tpl->assign('Service', _('Service'));
$tpl->assign('Output', _('Output'));
$tpl->assign('user', _('Users'));
$tpl->assign('Hostgroup', _('Hostgroup'));
$tpl->assign('Search', _('Search'));
$tpl->assign('ViewAll', _('Display Finished Downtimes'));
$tpl->assign('ViewDowntimeCycle', _('Display Recurring Downtimes'));
$tpl->assign('Author', _('Author'));
$tpl->assign('search_output', $search_output);
$tpl->assign('search_host', $host_name);
$tpl->assign('search_service', $search_service);
$tpl->assign('view_all', (int) $canViewAll);
$tpl->assign('view_downtime_cycle', (int) $canViewDowntimeCycle);
$tpl->assign('search_author', $search_author ?? '');

// Send Form
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());

// Display Page
$tpl->display('listDowntime.ihtml');
