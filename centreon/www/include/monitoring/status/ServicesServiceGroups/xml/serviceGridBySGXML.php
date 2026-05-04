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
CentreonSession::start();
$obj = new CentreonXMLBGRequest($dependencyInjector, session_id(), 1, 1, 0, 1);
$svcObj = new CentreonService($obj->DB);

if (! isset($obj->session_id) || ! CentreonSession::checkSession($obj->session_id, $obj->DB)) {
    echo 'Bad Session ID';

    exit();
}

// Set Default Poller
$obj->getDefaultFilters();

/**
 * @var Centreon $centreon
 */
$centreon = $_SESSION['centreon'];

/**
 * true: URIs will correspond to deprecated pages
 * false: URIs will correspond to new page (Resource Status)
 */
$useDeprecatedPages = $centreon->user->doesShowDeprecatedPages();

// Check Arguments From GET tab
$o = isset($_GET['o']) ? HtmlAnalyzer::sanitizeAndRemoveTags($_GET['o']) : 'h';
$p = filter_input(INPUT_GET, 'p', FILTER_VALIDATE_INT, ['options' => ['default' => 2]]);
$num = filter_input(INPUT_GET, 'num', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
$limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT, ['options' => ['default' => 20]]);
// if instance value is not set, displaying all active pollers linked resources
$instance = filter_var($obj->defaultPoller ?? -1, FILTER_VALIDATE_INT);
$hSearch = isset($_GET['host_search']) ? HtmlAnalyzer::sanitizeAndRemoveTags($_GET['host_search']) : '';
$sgSearch = isset($_GET['sg_search']) ? HtmlAnalyzer::sanitizeAndRemoveTags($_GET['sg_search']) : '';
$sort_type = isset($_GET['sort_type']) ? HtmlAnalyzer::sanitizeAndRemoveTags($_GET['sort_type']) : 'host_name';
$order = isset($_GET['order']) && $_GET['order'] === 'DESC' ? 'DESC' : 'ASC';

$kernel = App\Kernel::createForWeb();
$resourceController = $kernel->getContainer()->get(
    Centreon\Application\Controller\MonitoringResourceController::class
);

// saving bound values
$queryValues = [];
$queryValues2 = [];

$groupStr = implode(',', $obj->access->getAccessGroups()->getIds());

// Pre-fetch allowed service group IDs from config DB for non-admin users
$sgFilter = '';
if (! $obj->is_admin) {
    $allServiceGroupsAllowed = false;
    if ($groupStr === '') {
        $sgFilter = 'AND 1=0 ';
    } else {
        $query = <<<SQL
                SELECT 1 FROM acl_resources ar
                INNER JOIN acl_res_group_relations argr
                    ON argr.acl_res_id = ar.acl_res_id
                WHERE
                    argr.acl_group_id IN ({$groupStr})
                    AND ar.acl_res_activate = '1'
                    AND ar.all_servicegroups = '1'
                LIMIT 1
            SQL;

        try {
            $allServiceGroupsAllowed = $obj->DB->fetchAssociative($query) !== false;
        } catch (Adaptation\Database\Connection\Exception\ConnectionException $e) {
            throw new Core\Common\Domain\Exception\RepositoryException(
                message: 'Error while checking if all service groups are allowed: ' . $e->getMessage(),
                context: [
                    'query' => $query,
                    'groupStr' => $groupStr,
                ],
                previous: $e
            );
        }
    }

    if ($groupStr !== '' && ! $allServiceGroupsAllowed) {
        $allowedSgIds = [];
        $query = <<<SQL
                SELECT DISTINCT
                    arsr.sg_id
                FROM acl_resources_sg_relations arsr
                INNER JOIN acl_res_group_relations argr
                    ON argr.acl_res_id = arsr.acl_res_id
                WHERE argr.acl_group_id IN ({$groupStr})
            SQL;

        try {
            foreach ($obj->DB->iterateAssociative($query) as $row) {
                $allowedSgIds[] = (int) $row['sg_id'];
            }
        } catch (Adaptation\Database\Connection\Exception\ConnectionException $e) {
            throw new Core\Common\Domain\Exception\RepositoryException(
                message: 'Error while fetching allowed service group IDs: ' . $e->getMessage(),
                context: [
                    'query' => $query,
                    'groupStr' => $groupStr,
                ],
                previous: $e
            );
        }

        $sgFilter = $allowedSgIds === []
            ? 'AND 1=0 '
            : 'AND sg.servicegroup_id IN (' . implode(',', $allowedSgIds) . ') ';
    }
}

// Backup poller selection
$obj->setInstanceHistory($instance);

$_SESSION['monitoring_service_groups'] = $sgSearch;

// Filter on state
$s_search = '';

// Display service problems
if ($o == 'svcgridSG_pb' || $o == 'svcOVSG_pb') {
    $s_search .= ' AND s.state != 0 AND s.state != 4 ';
}

// Display acknowledged services
if ($o == 'svcgridSG_ack_1' || $o == 'svcOVSG_ack_1') {
    $s_search .= " AND s.acknowledged = '1' ";
}

// Display not acknowledged services
if ($o == 'svcgridSG_ack_0' || $o == 'svcOVSG_ack_0') {
    $s_search .= ' AND s.state != 0 AND s.state != 4 AND s.acknowledged = 0 ';
}

// this query allows to manage pagination
$query = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT 1 AS REALTIME, sg.servicegroup_id, h.host_id
    FROM servicegroups sg, services_servicegroups sgm, hosts h, services s ';

if (! $obj->is_admin && $groupStr !== '') {
    $query .= ', centreon_acl ';
}

$query .= 'WHERE sgm.servicegroup_id = sg.servicegroup_id
    AND sgm.host_id = h.host_id
    AND h.host_id = s.host_id
    AND sgm.service_id = s.service_id ';

// filter elements with acl (host, service, servicegroup)
if (! $obj->is_admin) {
    if ($groupStr !== '') {
        $query .= 'AND h.host_id = centreon_acl.host_id AND s.service_id = centreon_acl.service_id AND group_id IN (' . $groupStr . ') ';
    } else {
        $query .= 'AND 1=0 ';
    }
}
$query .= $sgFilter;

// Servicegroup search
if ($sgSearch != '') {
    $query .= ' AND sg.name = :sgSearch ';
    $queryValues['sgSearch'] = [
        PDO::PARAM_STR => $sgSearch,
    ];
}

// Host search
$h_search = '';
if ($hSearch != '') {
    $h_search .= ' AND h.name LIKE :hSearch ';
    // as this partial request is used in two queries, we need to bound it two times using two arrays
    // to avoid incoherent number of bound variables in the second query
    $queryValues['hSearch'] = $queryValues2['hSearch'] = [
        PDO::PARAM_STR => '%' . $hSearch . '%',
    ];
}
$query .= $h_search . $s_search;

// Poller search
if ($instance != -1) {
    $query .= ' AND h.instance_id = :instance ';
    $queryValues['instance'] = [
        PDO::PARAM_INT => $instance,
    ];
}
$query .= ' ORDER BY sg.name ' . $order . ' LIMIT :numLimit, :limit';
$queryValues['numLimit'] = [
    PDO::PARAM_INT => (int) ($num * $limit),
];
$queryValues['limit'] = [
    PDO::PARAM_INT => (int) $limit,
];

$dbResult = $obj->DBC->prepare($query);
foreach ($queryValues as $bindId => $bindData) {
    foreach ($bindData as $bindType => $bindValue) {
        $dbResult->bindValue($bindId, $bindValue, $bindType);
    }
}
$dbResult->execute();
$numRows = $obj->DBC->query('SELECT FOUND_ROWS() AS REALTIME')->fetchColumn();

// Create XML Flow
$obj->XML = new CentreonXML();
$obj->XML->startElement('reponse');
$obj->XML->startElement('i');
$obj->XML->writeElement('numrows', $numRows);
$obj->XML->writeElement('num', $num);
$obj->XML->writeElement('limit', $limit);
$obj->XML->writeElement('host_name', _('Hosts'), 0);
$obj->XML->writeElement('services', _('Services'), 0);
$obj->XML->writeElement('p', $p);
$obj->XML->writeElement('s', '1');
$obj->XML->endElement();

// Construct query for servicegroups search
$aTab = [];
$sg_search = '';
$aTab = [];
if ($numRows > 0) {
    $sg_search .= 'AND (';
    $servicegroups = [];
    while ($row = $dbResult->fetch()) {
        $servicesgroups[$row['servicegroup_id']][] = $row['host_id'];
    }
    $servicegroupsSql1 = [];
    foreach ($servicesgroups as $key => $value) {
        $hostsSql = [];
        foreach ($value as $hostId) {
            $hostsSql[] = $hostId;
        }
        $servicegroupsSql1[] = '(sg.servicegroup_id = ' . $key
            . ' AND h.host_id IN (' . implode(',', $hostsSql) . ')) ';
    }
    $sg_search .= implode(' OR ', $servicegroupsSql1);
    $sg_search .= ') ';
    if ($sgSearch != '') {
        $sg_search .= 'AND sg.name = :sgSearch';
        $queryValues2['sgSearch'] = [
            PDO::PARAM_STR => $sgSearch,
        ];
    }

    $query2 = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT 1 AS REALTIME, sg.name AS sg_name,
        sg.name AS alias,
        h.name AS host_name,
        h.state AS host_state,
        h.icon_image, h.host_id, s.state, s.description, s.service_id,
        (CASE s.state WHEN 0 THEN 3 WHEN 2 THEN 0 WHEN 3 THEN 2 ELSE s.state END) AS tri
        FROM servicegroups sg, services_servicegroups sgm, services s, hosts h ';

    if (! $obj->is_admin && $groupStr !== '') {
        $query2 .= ', centreon_acl ';
    }

    $query2 .= 'WHERE sgm.servicegroup_id = sg.servicegroup_id
        AND sgm.host_id = h.host_id
        AND h.host_id = s.host_id
        AND sgm.service_id = s.service_id ';

    // filter elements with acl (host, service, servicegroup)
    if (! $obj->is_admin) {
        if ($groupStr !== '') {
            $query2 .= 'AND h.host_id = centreon_acl.host_id AND s.service_id = centreon_acl.service_id AND group_id IN (' . $groupStr . ') ';
        } else {
            $query2 .= 'AND 1=0 ';
        }
    }
    $query2 .= $sgFilter . $sg_search . $h_search . $s_search . ' ORDER BY sg_name, tri ASC';

    $dbResult = $obj->DBC->prepare($query2);
    foreach ($queryValues2 as $bindId => $bindData) {
        foreach ($bindData as $bindType => $bindValue) {
            $dbResult->bindValue($bindId, $bindValue, $bindType);
        }
    }
    $dbResult->execute();

    $ct = 0;
    $sg = '';
    $h = '';
    $flag = 0;
    $count = 0;

    while ($tab = $dbResult->fetch()) {
        if (! isset($aTab[$tab['sg_name']])) {
            $aTab[$tab['sg_name']] = ['sgn' => CentreonUtils::escapeSecure($tab['sg_name']), 'o' => $ct, 'host' => []];
        }

        if (! isset($aTab[$tab['sg_name']]['host'][$tab['host_name']])) {
            $count++;
            $icone = $tab['icon_image'] ?: 'none';
            $aTab[$tab['sg_name']]['host'][$tab['host_name']] = ['h' => $tab['host_name'], 'hs' => _($obj->statusHost[$tab['host_state']]), 'hn' => CentreonUtils::escapeSecure($tab['host_name']), 'hico' => $icone, 'hnl' => CentreonUtils::escapeSecure(urlencode($tab['host_name'])), 'hid' => $tab['host_id'], 'hcount' => $count, 'hc' => $obj->colorHost[$tab['host_state']], 'service' => []];
        }

        if (! isset($aTab[$tab['sg_name']]['host'][$tab['host_name']]['service'][$tab['description']])) {
            $aTab[$tab['sg_name']]['host'][$tab['host_name']]['service'][$tab['description']] = ['sn' => CentreonUtils::escapeSecure($tab['description']), 'snl' => CentreonUtils::escapeSecure(urlencode($tab['description'])), 'sc' => $obj->colorService[$tab['state']], 'svc_id' => $tab['service_id']];
        }
        $ct++;
    }
}

foreach ($aTab as $key => $element) {
    $obj->XML->startElement('sg');
    $obj->XML->writeElement('sgn', $element['sgn']);
    $obj->XML->writeElement('o', $element['o']);
    foreach ($element['host'] as $host) {
        $obj->XML->startElement('h');
        $obj->XML->writeAttribute('class', $obj->getNextLineClass());
        $obj->XML->writeElement('hn', $host['hn'], false);
        $obj->XML->writeElement('hico', $host['hico']);
        $obj->XML->writeElement('hnl', $host['hnl']);
        $obj->XML->writeElement('hid', $host['hid']);
        $obj->XML->writeElement('hcount', $host['hcount']);
        $obj->XML->writeElement('hs', $host['hs']);
        $obj->XML->writeElement('hc', $host['hc']);
        $obj->XML->writeElement(
            'h_details_uri',
            $useDeprecatedPages
                ? 'main.php?p=20202&o=hd&host_name=' . $host['hn']
                : $resourceController->buildHostDetailsUri($host['hid'])
        );
        $obj->XML->writeElement(
            's_listing_uri',
            $useDeprecatedPages
                ? 'main.php?o=svc&p=20201&statusFilter=&host_search=' . $host['hn']
                : $resourceController->buildListingUri([
                    'filter' => json_encode([
                        'criterias' => [
                            [
                                'name' => 'search',
                                'object_type' => null,
                                'type' => 'text',
                                'value' => 'h.name:^' . $host['hn'] . '$',
                            ],
                        ],
                    ]),
                    'fromTopCounter' => 'true',
                ])
        );
        foreach ($host['service'] as $service) {
            $obj->XML->startElement('svc');
            $obj->XML->writeElement('sn', $service['sn']);
            $obj->XML->writeElement('snl', $service['snl']);
            $obj->XML->writeElement('sc', $service['sc']);
            $obj->XML->writeElement('svc_id', $service['svc_id']);
            $obj->XML->writeElement(
                's_details_uri',
                $useDeprecatedPages
                    ? 'main.php?o=svcd&p=202&host_name='
                        . $host['hn']
                        . '&amp;service_description='
                        . $service['sn']
                    : $resourceController->buildServiceDetailsUri($host['hid'], $service['svc_id'])
            );
            $obj->XML->endElement();
        }
        $obj->XML->writeElement('chartIcon', returnSvg('www/img/icons/chart.svg', 'var(--icons-fill-color)', 18, 18));
        $obj->XML->writeElement('viewIcon', returnSvg('www/img/icons/view.svg', 'var(--icons-fill-color)', 18, 18));
        $obj->XML->endElement();
        $count++;
    }

    $obj->XML->endElement();
}

$obj->XML->endElement();

// Send Header
$obj->header();

// Send XML
$obj->XML->output();
