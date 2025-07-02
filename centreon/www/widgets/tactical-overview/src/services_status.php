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

$dataCRI = [];
$dataWA = [];
$dataOK = [];
$dataUNK = [];
$dataPEND = [];
$db = new CentreonDB('centstorage');

/**
 * true: URIs will correspond to deprecated pages
 * false: URIs will correspond to new page (Resource Status)
 */
$useDeprecatedPages = $centreon->user->doesShowDeprecatedPages();

$buildServiceUri = function (array $states, array $statuses) use ($resourceController, $buildParameter) {
    return $resourceController->buildListingUri(
        [
            'filter' => json_encode(
                [
                    'criterias' => [
                        'resourceTypes' => [$buildParameter('service', 'Service')],
                        'states' => $states,
                        'statuses' => $statuses,
                    ],
                ]
            ),
        ]
    );
};

$pendingStatus = $buildParameter('PENDING', 'Pending');
$okStatus = $buildParameter('OK', 'Ok');
$warningStatus = $buildParameter('WARNING', 'Warning');
$criticalStatus = $buildParameter('CRITICAL', 'Critical');
$unknownStatus = $buildParameter('UNKNOWN', 'Unknown');

$unhandledState = $buildParameter('unhandled_problems', 'Unhandled');
$acknowledgedState = $buildParameter('acknowledged', 'Acknowledged');
$inDowntimeState = $buildParameter('in_downtime', 'In downtime');

$deprecatedServiceListingUri = '../../main.php?p=20201&search=';

// query for CRITICAL state
$res = $db->query(
    "SELECT 1 AS REALTIME,
        SUM(
            CASE WHEN s.state = 2
                AND s.enabled = 1
                AND h.enabled = 1
                AND h.name NOT LIKE '%Module%'
            THEN 1 ELSE 0 END
        ) AS status,
        SUM(
            CASE WHEN s.acknowledged = 1
                AND s.state = 2
                AND s.enabled = 1
                AND h.enabled = 1
                AND h.name NOT LIKE '%Module%'
            THEN 1 ELSE 0 END
        ) AS ack,
        SUM(
            CASE WHEN s.scheduled_downtime_depth = 1
                AND s.state = 2
                AND s.enabled = 1
                AND h.enabled = 1
                AND h.name NOT LIKE '%Module%'
            THEN 1 ELSE 0 END
        ) AS down,
        SUM(
            CASE WHEN s.state = 2
                AND (h.state = 1 OR h.state = 4 OR h.state = 2)
                AND s.enabled = 1
                AND h.enabled = 1
                AND h.name NOT LIKE '%Module%'
            THEN 1 ELSE 0 END
        ) AS pb,
        SUM(
            CASE WHEN s.state = 2
                AND s.enabled = 1
                AND h.enabled = 1
                AND h.name NOT LIKE '%Module%'
                AND s.acknowledged = 0
                AND s.scheduled_downtime_depth = 0
                AND h.state = 0
            THEN 1 ELSE 0 END
        ) AS un
    FROM services AS s
    LEFT JOIN hosts AS h ON h.host_id = s.host_id " . (
        $centreon->user->admin == 0
        ? 'JOIN (
            SELECT acl.host_id, acl.service_id
            FROM centreon_acl AS acl
            WHERE acl.group_id IN (' . ($grouplistStr != '' ? $grouplistStr : 0) . ')
            GROUP BY host_id,service_id
            ) x ON x.host_id = h.host_id AND x.service_id = s.service_id'
        : ''
    ) . ';'
);
while ($row = $res->fetch()) {
    $row['listing_uri'] = $useDeprecatedPages
        ? $deprecatedServiceListingUri . '&statusFilter=critical&o=svc'
        : $buildServiceUri([], [$criticalStatus]);

    $row['listing_ack_uri'] = $useDeprecatedPages
        ? $deprecatedServiceListingUri . '&statusFilter=critical&statusService=svcpb'
        : $buildServiceUri([$acknowledgedState], [$criticalStatus]);

    $row['listing_downtime_uri'] = $useDeprecatedPages
        ? $deprecatedServiceListingUri . '&statusFilter=critical&statusService=svcpb'
        : $buildServiceUri([$inDowntimeState], [$criticalStatus]);

    $row['listing_unhandled_uri'] = $useDeprecatedPages
        ? $deprecatedServiceListingUri . '&statusFilter=critical&statusService=svc_unhandled'
        : $buildServiceUri([$unhandledState], [$criticalStatus]);

    $dataCRI[] = $row;
}

// query for WARNING state
$res = $db->query(
    "SELECT 1 AS REALTIME,
        SUM(
            CASE WHEN s.state = 1
                AND s.enabled = 1
                AND h.enabled = 1
                AND h.name NOT LIKE '%Module%'
            THEN 1 ELSE 0 END
        ) AS status,
        SUM(
            CASE WHEN s.acknowledged = 1
                AND s.state = 1
                AND s.enabled = 1
                AND h.enabled = 1
                AND h.name NOT LIKE '%Module%'
            THEN 1 ELSE 0 END
        ) AS ack,
        SUM(
            CASE WHEN s.scheduled_downtime_depth > 0
                AND s.state = 1
                AND s.enabled = 1
                AND h.enabled = 1
                AND h.name NOT LIKE '%Module%'
            THEN 1 ELSE 0 END
        ) AS down,
        SUM(
            CASE WHEN s.state = 1
                AND (h.state = 1 OR h.state = 4 OR h.state = 2)
                AND s.enabled = 1
                AND h.enabled = 1
                AND h.name NOT LIKE '%Module%'
            THEN 1 ELSE 0 END
        ) AS pb,
        SUM(
            CASE WHEN s.state = 1
                AND s.enabled = 1
                AND h.enabled = 1
                AND h.name NOT LIKE '%Module%'
                AND s.acknowledged = 0
                AND s.scheduled_downtime_depth = 0
                AND h.state = 0
            THEN 1 ELSE 0 END
        ) AS un
    FROM services AS s
    LEFT JOIN hosts AS h ON h.host_id = s.host_id " . (
        $centreon->user->admin == 0
        ? 'JOIN (
            SELECT acl.host_id, acl.service_id
            FROM centreon_acl AS acl
            WHERE acl.group_id IN (' . ($grouplistStr != '' ? $grouplistStr : 0) . ')
            GROUP BY host_id,service_id
            ) x ON x.host_id = h.host_id AND x.service_id = s.service_id'
        : ''
    ) . ';'
);
while ($row = $res->fetch()) {
    $row['listing_uri'] = $useDeprecatedPages
        ? $deprecatedServiceListingUri . '&statusFilter=warning&o=svc'
        : $buildServiceUri([], [$warningStatus]);

    $row['listing_ack_uri'] = $useDeprecatedPages
        ? $deprecatedServiceListingUri . '&statusFilter=warning&statusService=svcpb'
        : $buildServiceUri([$acknowledgedState], [$warningStatus]);

    $row['listing_downtime_uri'] = $useDeprecatedPages
        ? $deprecatedServiceListingUri . '&statusFilter=warning&statusService=svcpb'
        : $buildServiceUri([$inDowntimeState], [$warningStatus]);

    $row['listing_unhandled_uri'] = $useDeprecatedPages
        ? $deprecatedServiceListingUri . '&statusFilter=critical&statusService=svc_unhandled'
        : $buildServiceUri([$unhandledState], [$warningStatus]);

    $dataWA[] = $row;
}

// query for OK state
$res = $db->query(
    "SELECT 1 AS REALTIME,
        SUM(
            CASE WHEN s.state = 0
                AND s.enabled = 1
                AND h.enabled = 1
                AND h.name NOT LIKE '%Module%'
            THEN 1 ELSE 0 END
        ) AS status
    FROM services AS s
    LEFT JOIN hosts AS h ON h.host_id = s.host_id " . (
        $centreon->user->admin == 0
        ? 'JOIN (
            SELECT acl.host_id, acl.service_id
            FROM centreon_acl AS acl
            WHERE acl.group_id IN (' . ($grouplistStr != '' ? $grouplistStr : 0) . ')
            GROUP BY host_id,service_id
            ) x ON x.host_id = h.host_id AND x.service_id = s.service_id'
        : ''
    ) . ';'
);
while ($row = $res->fetch()) {
    $row['listing_uri'] = $useDeprecatedPages
        ? $deprecatedServiceListingUri . '&statusFilter=ok&o=svc'
        : $buildServiceUri([], [$okStatus]);

    $dataOK[] = $row;
}

// query for PENDING state
$res = $db->query(
    "SELECT 1 AS REALTIME,
        SUM(
            CASE WHEN s.state = 4
                AND s.enabled = 1
                AND h.enabled = 1
                AND h.name NOT LIKE '%Module%'
            THEN 1 ELSE 0 END
        ) AS status
    FROM services AS s
    LEFT JOIN hosts AS h ON h.host_id = s.host_id " . (
        $centreon->user->admin == 0
        ? 'JOIN (
            SELECT acl.host_id, acl.service_id
            FROM centreon_acl AS acl
            WHERE acl.group_id IN (' . ($grouplistStr != '' ? $grouplistStr : 0) . ')
            GROUP BY host_id,service_id
            ) x ON x.host_id = h.host_id AND x.service_id = s.service_id'
        : ''
    ) . ';'
);
while ($row = $res->fetch()) {
    $row['listing_uri'] = $useDeprecatedPages
        ? $deprecatedServiceListingUri . '&statusFilter=pending&o=svc'
        : $buildServiceUri([], [$pendingStatus]);

    $dataPEND[] = $row;
}

// query for UNKNOWN state
$res = $db->query(
    "SELECT 1 AS REALTIME,
        SUM(
            CASE WHEN s.state = 3
                AND s.enabled = 1
                AND h.enabled = 1
                AND h.name NOT LIKE '%Module%'
            THEN 1 ELSE 0 END
        ) AS status,
        SUM(
            CASE WHEN s.acknowledged = 1
                AND s.state = 3
                AND s.enabled = 1
                AND h.enabled = 1
                AND h.name NOT LIKE '%Module%'
            THEN 1 ELSE 0 END
        ) AS ack,
        SUM(
            CASE WHEN s.scheduled_downtime_depth > 0
                AND s.state = 3
                AND s.enabled = 1
                AND h.enabled = 1
                AND h.name NOT LIKE '%Module%'
            THEN 1 ELSE 0 END
        ) AS down,
        SUM(
            CASE WHEN s.state = 3
                AND (h.state = 1 OR h.state = 4 OR h.state = 2)
                AND s.enabled = 1
                AND h.enabled = 1
                AND h.name NOT LIKE '%Module%'
            THEN 1 ELSE 0 END
        ) AS pb,
        SUM(
            CASE WHEN s.state = 3
                AND s.enabled = 1
                AND h.enabled = 1
                AND h.name NOT LIKE '%Module%'
                AND s.acknowledged = 0
                AND s.scheduled_downtime_depth = 0
                AND h.state = 0
            THEN 1 ELSE 0 END
        ) AS un
        FROM services AS s
        LEFT JOIN hosts AS h ON h.host_id = s.host_id " . (
        $centreon->user->admin == 0
            ? 'JOIN (
                SELECT acl.host_id, acl.service_id
                FROM centreon_acl AS acl
                WHERE acl.group_id IN (' . ($grouplistStr != '' ? $grouplistStr : 0) . ')
                GROUP BY host_id,service_id
                ) x ON x.host_id = h.host_id AND x.service_id = s.service_id'
            : ''
    ) . ';'
);
while ($row = $res->fetch()) {
    $row['listing_uri'] = $useDeprecatedPages
        ? $deprecatedServiceListingUri . '&statusFilter=unknown&o=svc'
        : $buildServiceUri([], [$unknownStatus]);

    $row['listing_ack_uri'] = $useDeprecatedPages
        ? $deprecatedServiceListingUri . '&statusFilter=unknown&statusService=svcpb'
        : $buildServiceUri([$acknowledgedState], [$unknownStatus]);

    $row['listing_downtime_uri'] = $useDeprecatedPages
        ? $deprecatedServiceListingUri . '&statusFilter=unknown&statusService=svcpb'
        : $buildServiceUri([$inDowntimeState], [$unknownStatus]);

    $row['listing_unhandled_uri'] = $useDeprecatedPages
        ? $deprecatedServiceListingUri . '&statusFilter=unknown&statusService=svc_unhandled'
        : $buildServiceUri([$unhandledState], [$unknownStatus]);

    $dataUNK[] = $row;
}

$numLine = 1;

$autoRefresh = (isset($preferences['refresh_interval']) && (int) $preferences['refresh_interval'] > 0)
    ? (int) $preferences['refresh_interval']
    : 30;

$template->assign('widgetId', $widgetId);
$template->assign('autoRefresh', $autoRefresh);
$template->assign('dataPEND', $dataPEND);
$template->assign('dataOK', $dataOK);
$template->assign('dataWA', $dataWA);
$template->assign('dataCRI', $dataCRI);
$template->assign('dataUNK', $dataUNK);
$template->display('services_status.ihtml');
