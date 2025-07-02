<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

ini_set('display_errors', 'Off');

require_once realpath(__DIR__ . '/../../../../../../bootstrap.php');
include_once _CENTREON_PATH_ . 'www/class/centreonUtils.class.php';
include_once _CENTREON_PATH_ . 'www/class/centreonXMLBGRequest.class.php';
include_once _CENTREON_PATH_ . 'www/include/monitoring/status/Common/common-Func.php';
include_once _CENTREON_PATH_ . 'www/include/common/common-Func.php';
include_once _CENTREON_PATH_ . 'www/class/centreonService.class.php';

// Create XML Request Objects
CentreonSession::start(1);
$centreonXMLBGRequest = new CentreonXMLBGRequest($dependencyInjector, session_id(), 1, 1, 0, 1);

if (
    ! isset($centreonXMLBGRequest->session_id)
    || ! CentreonSession::checkSession($centreonXMLBGRequest->session_id, $centreonXMLBGRequest->DB)
) {
    echo 'Bad Session ID';

    exit();
}

$centreon = $_SESSION['centreon'];

/**
 * true: URIs will correspond to deprecated pages
 * false: URIs will correspond to new page (Resource Status)
 */
$useDeprecatedPages = $centreon->user->doesShowDeprecatedPages();

// Set Default Poller
$centreonXMLBGRequest->getDefaultFilters();

// Check Arguments From GET tab
$o = isset($_GET['o']) ? HtmlAnalyzer::sanitizeAndRemoveTags($_GET['o']) : 'h';
$p = filter_input(INPUT_GET, 'p', FILTER_VALIDATE_INT, ['options' => ['default' => 2]]);
$num = filter_input(INPUT_GET, 'num', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
$limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT, ['options' => ['default' => 20]]);
// if instance value is not set, displaying all active pollers linked resources
$instance = filter_var($centreonXMLBGRequest->defaultPoller ?? -1, FILTER_VALIDATE_INT);
$hostgroups = filter_var($centreonXMLBGRequest->defaultHostgroups ?? 0, FILTER_VALIDATE_INT);
$search = isset($_GET['search']) ? HtmlAnalyzer::sanitizeAndRemoveTags($_GET['search']) : '';
$sortType = isset($_GET['sort_type']) ? HtmlAnalyzer::sanitizeAndRemoveTags($_GET['sort_type']) : 'host_name';
$order = isset($_GET['order']) && $_GET['order'] === 'DESC' ? 'DESC' : 'ASC';

// Backup poller selection
$centreonXMLBGRequest->setInstanceHistory($instance);

$kernel = App\Kernel::createForWeb();
/**
 * @var Centreon\Application\Controller\MonitoringResourceController $resourceController
 */
$resourceController = $kernel->getContainer()->get(
    Centreon\Application\Controller\MonitoringResourceController::class
);

// saving bound values
$queryValues = [];

/**
 * Get Host status
 */
$request = <<<'SQL_WRAP'
    SELECT SQL_CALC_FOUND_ROWS DISTINCT
      1 AS REALTIME, hosts.name, hosts.state, hosts.icon_image, hosts.host_id
    FROM hosts
    SQL_WRAP;

if ($hostgroups) {
    $request .= <<<'SQL'
        
        INNER JOIN hosts_hostgroups hhg
            ON hhg.host_id = hosts.host_id
            AND hhg.hostgroup_id = :hostgroup
        INNER JOIN hostgroups hg
            ON hg.hostgroup_id = hhg.hostgroup_id
        SQL;
    // only one value is returned from the current "select" filter
    $queryValues['hostgroup'] = [PDO::PARAM_INT =>  $hostgroups];
}

if (! $centreonXMLBGRequest->is_admin) {
    $request .= <<<SQL
            
            INNER JOIN centreon_acl
                ON centreon_acl.host_id = hosts.host_id
                AND centreon_acl.group_id IN ({$centreonXMLBGRequest->grouplistStr})
        SQL;
}

$request .= " WHERE hosts.name NOT LIKE '\_Module\_%' ";
if ($o == 'svcgrid_pb' || $o == 'svcOV_pb' || $o == 'svcgrid_ack_0' || $o == 'svcOV_ack_0') {
    $request .= <<<'SQL'
        
        AND hosts.host_id IN (
          SELECT s.host_id
          FROM services s
          WHERE s.state != 0
            AND s.state != 4
            AND s.enabled = 1
        )
        SQL;
}
if ($o == 'svcgrid_ack_1' || $o == 'svcOV_ack_1') {
    $request .= <<<'SQL'
        
        AND hosts.host_id IN (
            SELECT s.host_id
            FROM services s
            WHERE s.acknowledged = '1'
            AND s.enabled = 1
        )
        SQL;
}
if ($search != '') {
    $request .= ' AND hosts.name like :search ';
    $queryValues['search'] = [PDO::PARAM_STR => '%' . $search . '%'];
}
if ($instance != -1) {
    $request .= ' AND hosts.instance_id = :instance ';
    $queryValues['instance'] = [PDO::PARAM_INT =>  $instance];
}

$request .= ' AND hosts.enabled = 1 ';

switch ($sortType) {
    case 'current_state':
        $request .= ' ORDER BY hosts.state ' . $order . ',hosts.name ';
        break;
    default:
        $request .= ' ORDER BY hosts.name ' . $order;
        break;
}
$request .= ' LIMIT :numLimit, :limit';
$queryValues['numLimit'] = [PDO::PARAM_INT => ($num * $limit)];
$queryValues['limit'] = [PDO::PARAM_INT => $limit];

// Execute request
$dbResult = $centreonXMLBGRequest->DBC->prepare($request);
foreach ($queryValues as $bindId => $bindData) {
    foreach ($bindData as $bindType => $bindValue) {
        $dbResult->bindValue($bindId, $bindValue, $bindType);
    }
}
$dbResult->execute();

$numRows = (int) $centreonXMLBGRequest->DBC->query('SELECT FOUND_ROWS() AS REALTIME')->fetchColumn();

$centreonXMLBGRequest->XML->startElement('reponse');
$centreonXMLBGRequest->XML->startElement('i');
$centreonXMLBGRequest->XML->writeElement('numrows', $numRows);
$centreonXMLBGRequest->XML->writeElement('num', $num);
$centreonXMLBGRequest->XML->writeElement('limit', $limit);
$centreonXMLBGRequest->XML->writeElement('p', $p);

preg_match('/svcOV/', $_GET['o'], $matches)
    ? $centreonXMLBGRequest->XML->writeElement('s', '1')
    : $centreonXMLBGRequest->XML->writeElement('s', '0');

$centreonXMLBGRequest->XML->endElement();

$tab_final = [];
$hostIds = [];

while ($ndo = $dbResult->fetch()) {
    $hostIds[] = $ndo['host_id'];
    $tab_final[$ndo['name']] = ['cs' => $ndo['state'], 'hid' => $ndo['host_id']];
    $tabIcone[$ndo['name']] = $ndo['icon_image'] != '' ? $ndo['icon_image'] : 'none';
}
$dbResult->closeCursor();

// Get Service status
$tab_svc = $centreonXMLBGRequest->monObj->getServiceStatus($hostIds, $centreonXMLBGRequest, $o, $instance);
if (isset($tab_svc)) {
    foreach ($tab_svc as $host_name => $tab) {
        if (count($tab)) {
            $tab_final[$host_name]['tab_svc'] = $tab;
        }
    }
}

$ct = 0;
if (isset($tab_svc)) {
    foreach ($tab_final as $host_name => $tab) {
        $centreonXMLBGRequest->XML->startElement('l');
        $centreonXMLBGRequest->XML->writeAttribute('class', $centreonXMLBGRequest->getNextLineClass());
        if (isset($tab['tab_svc'])) {
            foreach ($tab['tab_svc'] as $svc => $details) {
                $state = $details['state'];
                $serviceId = $details['service_id'];
                $centreonXMLBGRequest->XML->startElement('svc');
                $centreonXMLBGRequest->XML->writeElement('sn', CentreonUtils::escapeSecure($svc), false);
                $centreonXMLBGRequest->XML->writeElement('snl', CentreonUtils::escapeSecure(urlencode($svc)));
                $centreonXMLBGRequest->XML->writeElement('sc', $centreonXMLBGRequest->colorService[$state]);
                $centreonXMLBGRequest->XML->writeElement('svc_id', $serviceId);
                $centreonXMLBGRequest->XML->writeElement(
                    's_details_uri',
                    $useDeprecatedPages
                        ? 'main.php?o=svcd&p=202&host_name=' . $host_name . '&service_description=' . $svc
                        : $resourceController->buildServiceDetailsUri($tab['hid'], $serviceId)
                );
                $centreonXMLBGRequest->XML->endElement();
            }
        }
        $centreonXMLBGRequest->XML->writeElement('o', $ct++);
        $centreonXMLBGRequest->XML->writeElement('ico', $tabIcone[$host_name]);
        $centreonXMLBGRequest->XML->writeElement('hn', $host_name, false);
        $centreonXMLBGRequest->XML->writeElement('hid', $tab['hid'], false);
        $centreonXMLBGRequest->XML->writeElement('hnl', CentreonUtils::escapeSecure(urlencode($host_name)));
        $centreonXMLBGRequest->XML->writeElement('hs', _($centreonXMLBGRequest->statusHost[$tab['cs']]), false);
        $centreonXMLBGRequest->XML->writeElement('hc', $centreonXMLBGRequest->colorHost[$tab['cs']]);
        $centreonXMLBGRequest->XML->writeElement(
            'h_details_uri',
            $useDeprecatedPages
                ? 'main.php?p=20202&o=hd&host_name=' . $host_name
                : $resourceController->buildHostDetailsUri($tab['hid'])
        );
        $centreonXMLBGRequest->XML->writeElement(
            's_listing_uri',
            $useDeprecatedPages
                ? 'main.php?o=svc&p=20201&statusFilter=;host_search=' . $host_name
                : $resourceController->buildListingUri([
                    'filter' => json_encode([
                        'criterias' => [
                            'search' => 'h.name:^' . $host_name . '$',
                        ],
                    ]),
                ])
        );
        $centreonXMLBGRequest->XML->writeElement(
            'chartIcon',
            returnSvg('www/img/icons/chart.svg', 'var(--icons-fill-color)', 18, 18)
        );
        $centreonXMLBGRequest->XML->writeElement(
            'viewIcon',
            returnSvg('www/img/icons/view.svg', 'var(--icons-fill-color)', 18, 18)
        );
        $centreonXMLBGRequest->XML->endElement();
    }
}

if (! $ct) {
    $centreonXMLBGRequest->XML->writeElement('infos', 'none');
}
$centreonXMLBGRequest->XML->endElement();

// Send Header
$centreonXMLBGRequest->header();

// Send XML
$centreonXMLBGRequest->XML->output();
