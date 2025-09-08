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

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\Enum\QueryParameterTypeEnum;

$hostsDownStatus = [];
$hostsUnknownStatus = [];
$hostsUpStatus = [];
$hostsPendingStatus = [];
$dataList = [];

$realtimeConnection = new CentreonDB('centstorage');

/**
 * true: URIs will correspond to deprecated pages
 * false: URIs will correspond to new page (Resource Status)
 */
$useDeprecatedPages = $centreon->user->doesShowDeprecatedPages();

$autoRefresh = (isset($preferences['refresh_interval']) && (int) $preferences['refresh_interval'] > 0)
    ? (int) $preferences['refresh_interval']
    : 30;

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

$queryParameters = [];
$aclSubQuery = '';

if (! $centreon->user->admin) {
    $acls = new CentreonAclLazy($centreon->user->user_id);

    // Make request return nothing
    if ($acls->getAccessGroups()->isEmpty()) {
        $aclSubQuery = ' WHERE 1 = 0';
    } else {
        ['parameters' => $queryParameters, 'placeholderList' => $bindQuery] = createMultipleBindParameters(
            $acls->getAccessGroups()->getIds(),
            'access_group',
            QueryParameterTypeEnum::INTEGER
        );

        $aclSubQuery = <<<SQL
                    INNER JOIN centreon_acl acl
                        ON acl.host_id = h.host_id
                        AND acl.service_id IS NULL
                    WHERE acl.group_id IN ({$bindQuery})
            SQL;
    }
}

$downStatusQuery = <<<SQL
        SELECT 1 AS REALTIME,
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
        FROM hosts AS h
        {$aclSubQuery}
    SQL;

foreach ($realtimeConnection->iterateAssociative($downStatusQuery, QueryParameters::create($queryParameters)) as $record) {
    $record['un'] = $record['status'] - ($record['ack'] + $record['down']);

    $deprecatedDownHostListingUri = $deprecatedHostListingUri . 'down';

    $record['listing_uri'] = $useDeprecatedPages
        ? $deprecatedDownHostListingUri
        : $buildHostUri([], [$downStatus]);

    $record['listing_ack_uri'] = $useDeprecatedPages
        ? $deprecatedDownHostListingUri
        : $buildHostUri([$acknowledgedState], [$downStatus]);

    $record['listing_downtime_uri'] = $useDeprecatedPages
        ? $deprecatedDownHostListingUri
        : $buildHostUri([$inDowntimeState], [$downStatus]);

    $record['listing_unhandled_uri'] = $useDeprecatedPages
        ? $deprecatedDownHostListingUri
        : $buildHostUri([$unhandledState], [$downStatus]);

    $hostsDownStatus[] = $record;
}

$query = <<<SQL
        SELECT 1 AS REALTIME,
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
                    AND h.name NOT LIKE '%Module%'
                    AND h.scheduled_downtime_depth = 1
                THEN 1 ELSE 0 END
            ) as down
        FROM hosts AS h
        {$aclSubQuery}
    SQL;

foreach ($realtimeConnection->iterateAssociative($query, QueryParameters::create($queryParameters)) as $record) {
    $record['un'] = $record['status'] - ($record['ack'] + $record['down']);

    $deprecatedUnreachableHostListingUri = $deprecatedHostListingUri . 'unreachable';

    $record['listing_uri'] = $useDeprecatedPages
        ? $deprecatedUnreachableHostListingUri
        : $buildHostUri([], [$unreachableStatus]);

    $record['listing_ack_uri'] = $useDeprecatedPages
        ? $deprecatedUnreachableHostListingUri
        : $buildHostUri([$acknowledgedState], [$unreachableStatus]);

    $record['listing_downtime_uri'] = $useDeprecatedPages
        ? $deprecatedUnreachableHostListingUri
        : $buildHostUri([$inDowntimeState], [$unreachableStatus]);

    $record['listing_unhandled_uri'] = $useDeprecatedPages
        ? $deprecatedUnreachableHostListingUri
        : $buildHostUri([$unhandledState], [$unreachableStatus]);

    $hostsUnknownStatus[] = $record;
}

$query = <<<SQL
        SELECT 1 AS REALTIME,
            SUM(
                CASE WHEN h.state = 0
                    AND h.enabled = 1
                    AND h.name NOT LIKE '%Module%'
                THEN 1 ELSE 0 END
            ) as status
        FROM hosts AS h
        {$aclSubQuery}
    SQL;

foreach ($realtimeConnection->iterateAssociative($query, QueryParameters::create($queryParameters)) as $record) {
    $record['listing_uri'] = $useDeprecatedPages
        ? $deprecatedHostListingUri . 'up'
        : $buildHostUri([], [$upStatus]);

    $hostsUpStatus[] = $record;
}

$query = <<<SQL
        SELECT 1 AS REALTIME,
            SUM(
                CASE WHEN h.state = 4
                    AND h.enabled = 1
                    AND h.name NOT LIKE '%Module%'
                THEN 1 ELSE 0 END
            ) as status
        FROM hosts AS h
        {$aclSubQuery}
    SQL;

foreach ($realtimeConnection->iterateAssociative($query, QueryParameters::create($queryParameters)) as $record) {
    $record['listing_uri'] = $useDeprecatedPages
        ? $deprecatedHostListingUri . 'pending'
        : $buildHostUri([], [$pendingStatus]);

    $hostsPendingStatus[] = $record;
}

$template->assign('preferences', $preferences);
$template->assign('widgetId', $widgetId);
$template->assign('autoRefresh', $autoRefresh);
$template->assign('dataPEND', $hostsPendingStatus);
$template->assign('dataUP', $hostsUpStatus);
$template->assign('dataUN', $hostsUnknownStatus);
$template->assign('dataDO', $hostsDownStatus);
$template->display('hosts_status.ihtml');
