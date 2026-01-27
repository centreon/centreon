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

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\Enum\QueryParameterTypeEnum;

require_once $centreon_path . 'www/include/common/sqlCommonFunction.php';

$serviceCriticalStatus = [];
$serviceWarningStatus = [];
$serviceOkStatus = [];
$serviceUnknownStatus = [];
$servicePendingStatus = [];

$db = new CentreonDB('centstorage');

/**
 * true: URIs will correspond to deprecated pages
 * false: URIs will correspond to new page (Resource Status)
 */
$useDeprecatedPages = $centreon->user->doesShowDeprecatedPages();

$autoRefresh = (isset($preferences['refresh_interval']) && (int) $preferences['refresh_interval'] > 0)
    ? (int) $preferences['refresh_interval']
    : 30;

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

$queryParameters = [];

$aclSubQuery = '';
if (! $centreon->user->admin) {
    $acls = new CentreonAclLazy($centreon->user->user_id);
    ['parameters' => $queryParameters, 'placeholderList' => $bindQuery] = createMultipleBindParameters(
        $acls->getAccessGroups()->getIds(),
        'access_group',
        QueryParameterTypeEnum::INTEGER
    );

    $aclSubQuery = <<<SQL
            INNER JOIN centreon_acl acl
                ON acl.host_id = h.host_id
                AND acl.service_id = s.service_id
            WHERE acl.group_id IN ({$bindQuery})
        SQL;
}

$query = <<<SQL
        SELECT 1 AS REALTIME,
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
        LEFT JOIN hosts AS h
            ON h.host_id = s.host_id
        {$aclSubQuery}
    SQL;

foreach ($db->iterateAssociative($query, QueryParameters::create($queryParameters)) as $record) {
    $record['listing_uri'] = $useDeprecatedPages
        ? $deprecatedServiceListingUri . '&statusFilter=critical&o=svc'
        : $buildServiceUri([], [$criticalStatus]);

    $record['listing_ack_uri'] = $useDeprecatedPages
        ? $deprecatedServiceListingUri . '&statusFilter=critical&statusService=svcpb'
        : $buildServiceUri([$acknowledgedState], [$criticalStatus]);

    $record['listing_downtime_uri'] = $useDeprecatedPages
        ? $deprecatedServiceListingUri . '&statusFilter=critical&statusService=svcpb'
        : $buildServiceUri([$inDowntimeState], [$criticalStatus]);

    $record['listing_unhandled_uri'] = $useDeprecatedPages
        ? $deprecatedServiceListingUri . '&statusFilter=critical&statusService=svc_unhandled'
        : $buildServiceUri([$unhandledState], [$criticalStatus]);

    $serviceCriticalStatus[] = $record;
}

$query = <<<SQL
        SELECT 1 AS REALTIME,
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
        LEFT JOIN hosts AS h
            ON h.host_id = s.host_id
        {$aclSubQuery}
    SQL;

foreach ($db->iterateAssociative($query, QueryParameters::create($queryParameters)) as $record) {
    $record['listing_uri'] = $useDeprecatedPages
        ? $deprecatedServiceListingUri . '&statusFilter=warning&o=svc'
        : $buildServiceUri([], [$warningStatus]);

    $record['listing_ack_uri'] = $useDeprecatedPages
        ? $deprecatedServiceListingUri . '&statusFilter=warning&statusService=svcpb'
        : $buildServiceUri([$acknowledgedState], [$warningStatus]);

    $record['listing_downtime_uri'] = $useDeprecatedPages
        ? $deprecatedServiceListingUri . '&statusFilter=warning&statusService=svcpb'
        : $buildServiceUri([$inDowntimeState], [$warningStatus]);

    $record['listing_unhandled_uri'] = $useDeprecatedPages
        ? $deprecatedServiceListingUri . '&statusFilter=critical&statusService=svc_unhandled'
        : $buildServiceUri([$unhandledState], [$warningStatus]);

    $serviceWarningStatus[] = $record;
}

$query = <<<SQL
        SELECT 1 AS REALTIME,
            SUM(
                CASE WHEN s.state = 0
                    AND s.enabled = 1
                    AND h.enabled = 1
                    AND h.name NOT LIKE '%Module%'
                THEN 1 ELSE 0 END
            ) AS status
        FROM services AS s
        LEFT JOIN hosts AS h
            ON h.host_id = s.host_id
        {$aclSubQuery}
    SQL;

foreach ($db->iterateAssociative($query, QueryParameters::create($queryParameters)) as $record) {
    $record['listing_uri'] = $useDeprecatedPages
        ? $deprecatedServiceListingUri . '&statusFilter=ok&o=svc'
        : $buildServiceUri([], [$okStatus]);

    $serviceOkStatus[] = $record;
}

$query = <<<SQL
        SELECT 1 AS REALTIME,
            SUM(
                CASE WHEN s.state = 4
                    AND s.enabled = 1
                    AND h.enabled = 1
                    AND h.name NOT LIKE '%Module%'
                THEN 1 ELSE 0 END
            ) AS status
        FROM services AS s
        LEFT JOIN hosts AS h
            ON h.host_id = s.host_id
        {$aclSubQuery}
    SQL;

foreach ($db->iterateAssociative($query, QueryParameters::create($queryParameters)) as $record) {
    $record['listing_uri'] = $useDeprecatedPages
        ? $deprecatedServiceListingUri . '&statusFilter=pending&o=svc'
        : $buildServiceUri([], [$pendingStatus]);

    $servicePendingStatus[] = $record;
}

$query = <<<SQL
        SELECT 1 AS REALTIME,
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
        LEFT JOIN hosts AS h
            ON h.host_id = s.host_id
        {$aclSubQuery}
    SQL;

foreach ($db->iterateAssociative($query, QueryParameters::create($queryParameters)) as $record) {
    $record['listing_uri'] = $useDeprecatedPages
        ? $deprecatedServiceListingUri . '&statusFilter=unknown&o=svc'
        : $buildServiceUri([], [$unknownStatus]);

    $record['listing_ack_uri'] = $useDeprecatedPages
        ? $deprecatedServiceListingUri . '&statusFilter=unknown&statusService=svcpb'
        : $buildServiceUri([$acknowledgedState], [$unknownStatus]);

    $record['listing_downtime_uri'] = $useDeprecatedPages
        ? $deprecatedServiceListingUri . '&statusFilter=unknown&statusService=svcpb'
        : $buildServiceUri([$inDowntimeState], [$unknownStatus]);

    $record['listing_unhandled_uri'] = $useDeprecatedPages
        ? $deprecatedServiceListingUri . '&statusFilter=unknown&statusService=svc_unhandled'
        : $buildServiceUri([$unhandledState], [$unknownStatus]);

    $serviceUnknownStatus[] = $record;
}

$template->assign('widgetId', $widgetId);
$template->assign('autoRefresh', $autoRefresh);
$template->assign('dataPEND', $servicePendingStatus);
$template->assign('dataOK', $serviceOkStatus);
$template->assign('dataWA', $serviceWarningStatus);
$template->assign('dataCRI', $serviceCriticalStatus);
$template->assign('dataUNK', $serviceUnknownStatus);
$template->display('services_status.ihtml');
