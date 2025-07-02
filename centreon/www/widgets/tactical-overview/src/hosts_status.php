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

$dataDO = [];
$dataUN = [];
$dataUP = [];
$dataPEND = [];
$dataList = [];
$db = new CentreonDB('centstorage');

/**
 * true: URIs will correspond to deprecated pages
 * false: URIs will correspond to new page (Resource Status)
 */
$useDeprecatedPages = $centreon->user->doesShowDeprecatedPages();

$buildHostUri = function (array $states, array $statuses) use ($resourceController, $buildParameter) {
    return $resourceController->buildListingUri(
        [
            'filter' => json_encode(
                [
                    'criterias' => [
                        'resourceTypes' => [$buildParameter('host', 'Host')],
                        'states' => $states,
                        'statuses' => $statuses,
                    ],
                ]
            ),
        ]
    );
};

$pendingStatus = $buildParameter('PENDING', 'Pending');
$upStatus = $buildParameter('UP', 'Up');
$downStatus = $buildParameter('DOWN', 'Down');
$unreachableStatus = $buildParameter('UNREACHABLE', 'Unreachable');

$unhandledState = $buildParameter('unhandled_problems', 'Unhandled');
$acknowledgedState = $buildParameter('acknowledged', 'Acknowledged');
$inDowntimeState = $buildParameter('in_downtime', 'In downtime');

$deprecatedHostListingUri = '../../main.php?p=20202&search=&o=h_';

// query for DOWN status
$res = $db->query(
    "SELECT 1 AS REALTIME,
        SUM(
            CASE WHEN h.state = 1
                AND h.enabled = 1
                AND h.name NOT LIKE '%Module%'
            THEN 1 ELSE 0 END
        ) as status,
        SUM(
            CASE WHEN h.acknowledged = 1
                AND h.state = 1
                AND h.enabled = 1
                AND h.name NOT LIKE '%Module%'
            THEN 1 ELSE 0 END
        ) as ack,
        SUM(
            CASE WHEN h.scheduled_downtime_depth = 1
                AND h.state = 1
                AND h.enabled = 1
                AND h.name NOT LIKE '%Module%'
            THEN 1 ELSE 0 END
        ) as down
    FROM hosts AS h " . (
        $centreon->user->admin == 0
        ? 'JOIN (
            SELECT acl.host_id, acl.service_id
            FROM centreon_acl AS acl
            WHERE acl.group_id IN (' . ($grouplistStr != '' ? $grouplistStr : 0) . ')
            GROUP BY host_id
        ) x ON x.host_id = h.host_id AND x.service_id IS NULL' : ''
    ) . ';'
);
while ($row = $res->fetch()) {
    $row['un'] = $row['status'] - ($row['ack'] + $row['down']);

    $deprecatedDownHostListingUri = $deprecatedHostListingUri . 'down';

    $row['listing_uri'] = $useDeprecatedPages
        ? $deprecatedDownHostListingUri
        : $buildHostUri([], [$downStatus]);

    $row['listing_ack_uri'] = $useDeprecatedPages
        ? $deprecatedDownHostListingUri
        : $buildHostUri([$acknowledgedState], [$downStatus]);

    $row['listing_downtime_uri'] = $useDeprecatedPages
        ? $deprecatedDownHostListingUri
        : $buildHostUri([$inDowntimeState], [$downStatus]);

    $row['listing_unhandled_uri'] = $useDeprecatedPages
        ? $deprecatedDownHostListingUri
        : $buildHostUri([$unhandledState], [$downStatus]);

    $dataDO[] = $row;
}

// query for UNKNOWN status
$res = $db->query(
    "SELECT 1 AS REALTIME,
        SUM(
            CASE WHEN h.state = 2
                AND h.enabled = 1
                AND h.name NOT LIKE '%Module%'
            THEN 1 ELSE 0 END
        ) as status,
        SUM(
            CASE WHEN h.acknowledged = 1
                AND h.state = 2
                AND h.enabled = 1
                AND h.name NOT LIKE '%Module%'
            THEN 1 ELSE 0 END
        ) as ack,
        SUM(
            CASE WHEN h.state = 2
                AND h.enabled = 1
                AND h.name NOT LIKE '%Module% '
                AND h.scheduled_downtime_depth = 1
            THEN 1 ELSE 0 END
        ) as down
    FROM hosts AS h " . (
        $centreon->user->admin == 0
        ? 'JOIN (
            SELECT acl.host_id, acl.service_id
            FROM centreon_acl AS acl
            WHERE acl.group_id IN (' . ($grouplistStr != '' ? $grouplistStr : 0) . ')
            GROUP BY host_id
        ) x ON x.host_id = h.host_id AND x.service_id IS NULL' : ''
    ) . ';'
);
while ($row = $res->fetch()) {
    $row['un'] = $row['status'] - ($row['ack'] + $row['down']);

    $deprecatedUnreachableHostListingUri = $deprecatedHostListingUri . 'unreachable';

    $row['listing_uri'] = $useDeprecatedPages
        ? $deprecatedUnreachableHostListingUri
        : $buildHostUri([], [$unreachableStatus]);

    $row['listing_ack_uri'] = $useDeprecatedPages
        ? $deprecatedUnreachableHostListingUri
        : $buildHostUri([$acknowledgedState], [$unreachableStatus]);

    $row['listing_downtime_uri'] = $useDeprecatedPages
        ? $deprecatedUnreachableHostListingUri
        : $buildHostUri([$inDowntimeState], [$unreachableStatus]);

    $row['listing_unhandled_uri'] = $useDeprecatedPages
        ? $deprecatedUnreachableHostListingUri
        : $buildHostUri([$unhandledState], [$unreachableStatus]);

    $dataUN[] = $row;
}

// query for UP status
$res = $db->query(
    "SELECT 1 AS REALTIME,
        SUM(
            CASE WHEN h.state = 0
                AND h.enabled = 1
                AND h.name NOT LIKE '%Module%'
            THEN 1 ELSE 0 END
        ) as status
    FROM hosts AS h " . (
        $centreon->user->admin == 0
        ? 'JOIN (
            SELECT acl.host_id, acl.service_id
            FROM centreon_acl AS acl
            WHERE acl.group_id IN (' . ($grouplistStr != '' ? $grouplistStr : 0) . ')
            GROUP BY host_id
        ) x ON x.host_id = h.host_id AND x.service_id IS NULL' : ''
    ) . ';'
);
while ($row = $res->fetch()) {
    $row['listing_uri'] = $useDeprecatedPages
        ? $deprecatedHostListingUri . 'up'
        : $buildHostUri([], [$upStatus]);

    $dataUP[] = $row;
}

// query for PENDING status
$res = $db->query(
    "SELECT 1 AS REALTIME,
        SUM(
            CASE WHEN h.state = 4
                AND h.enabled = 1
                AND h.name NOT LIKE '%Module%'
            THEN 1 ELSE 0 END
        ) as status
    FROM hosts AS h " . (
        $centreon->user->admin == 0
        ? 'JOIN (
            SELECT acl.host_id, acl.service_id
            FROM centreon_acl AS acl
            WHERE acl.group_id IN (' . ($grouplistStr != '' ? $grouplistStr : 0) . ')
            GROUP BY host_id
        ) x ON x.host_id = h.host_id AND x.service_id IS NULL' : ''
    ) . ';'
);
while ($row = $res->fetch()) {
    $row['listing_uri'] = $useDeprecatedPages
        ? $deprecatedHostListingUri . 'pending'
        : $buildHostUri([], [$pendingStatus]);

    $dataPEND[] = $row;
}

$numLine = 1;

$autoRefresh = (isset($preferences['refresh_interval']) && (int) $preferences['refresh_interval'] > 0)
    ? (int) $preferences['refresh_interval']
    : 30;

$template->assign('preferences', $preferences);
$template->assign('widgetId', $widgetId);
$template->assign('autoRefresh', $autoRefresh);
$template->assign('dataPEND', $dataPEND);
$template->assign('dataUP', $dataUP);
$template->assign('dataUN', $dataUN);
$template->assign('dataDO', $dataDO);
$template->display('hosts_status.ihtml');
